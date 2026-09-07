<?php

declare(strict_types=1);

namespace App\Service\Ai;

use App\Entity\Audit;
use App\Entity\AuditFinding;
use App\Entity\Enum\FindingCategory;
use App\Entity\RepositoryInventory;

/**
 * Builds the JSON payloads sent to the ai-engine, in the snake_case shape
 * its pydantic request schemas expect (see ai-engine/app/schemas/requests.py).
 *
 * Only ever sends deterministic data already persisted by the Phase 3/4/5
 * pipeline. There is no source-code access here: the ingested workspace is
 * deleted once a scan finishes (see GitHubIngestionService::cleanup()), so
 * findings/inventory metadata are the only ground truth available once an
 * audit exists — see docs/ai.md.
 */
final class AiPayloadBuilder
{
    private const MAX_FINDINGS_PER_REQUEST = 50;

    /** @return array<string, mixed> */
    public function repository(Audit $audit): array
    {
        $repositoryScan = $audit->getRepositoryScan();
        $repository = $repositoryScan->getRepository();
        $inventory = $repositoryScan->getInventory();

        return [
            'name' => $repository->getName(),
            'primary_language' => $this->primaryLanguage($inventory),
            'total_files' => $inventory?->getTotalFiles() ?? 0,
            'total_size_bytes' => $inventory?->getTotalSizeBytes() ?? 0,
            'language_stats' => $this->jsonObject($inventory?->getLanguageStats() ?? []),
            'metadata_flags' => $this->jsonObject($inventory?->getMetadata() ?? []),
        ];
    }

    /** @return array<string, mixed> */
    public function auditSummary(Audit $audit): array
    {
        $categoryScores = [];
        foreach ($audit->getScores() as $score) {
            $categoryScores[$score->getCategory()->value] = $score->getScore();
        }

        return [
            'overall_score' => $audit->getOverallScore(),
            'category_scores' => $this->jsonObject($categoryScores),
        ];
    }

    /**
     * PHP can't distinguish an empty associative array from an empty list —
     * both are `[]`, and json_encode() renders both as a JSON array. The
     * ai-engine's pydantic schemas require a JSON *object* for these fields
     * (dict[str, Any]), so an empty map must be forced to encode as `{}`.
     *
     * @param array<string, mixed> $value
     */
    private function jsonObject(array $value): array|\stdClass
    {
        return [] === $value ? new \stdClass() : $value;
    }

    /** @return array<string, mixed> */
    public function finding(AuditFinding $finding): array
    {
        return [
            'id' => (string) $finding->getId(),
            'rule_id' => $finding->getRuleId(),
            'category' => $finding->getCategory()->value,
            'severity' => $finding->getSeverity()->value,
            'title' => $finding->getTitle(),
            'description' => $finding->getDescription(),
            'file_path' => $finding->getFilePath(),
            'start_line' => $finding->getStartLine(),
            'end_line' => $finding->getEndLine(),
            'recommendation' => $finding->getRecommendation(),
            'confidence' => $finding->getConfidence() ?? 0.0,
            'priority' => $finding->getPriority(),
        ];
    }

    /**
     * Top findings by priority/confidence, capped so the request payload
     * stays reasonable — the ai-engine's context builder applies its own
     * character budget on top of this as a second line of defense.
     *
     * @return list<array<string, mixed>>
     */
    public function topFindings(Audit $audit, ?FindingCategory $onlyCategory = null): array
    {
        $findings = $audit->getFindings()->filter(
            fn (AuditFinding $finding) => null === $onlyCategory || $finding->getCategory() === $onlyCategory,
        )->toArray();

        usort($findings, static function (AuditFinding $a, AuditFinding $b): int {
            return [$b->getPriority(), $b->getConfidence() ?? 0.0] <=> [$a->getPriority(), $a->getConfidence() ?? 0.0];
        });

        $findings = \array_slice($findings, 0, self::MAX_FINDINGS_PER_REQUEST);

        return array_map($this->finding(...), $findings);
    }

    private function primaryLanguage(?RepositoryInventory $inventory): ?string
    {
        if (null === $inventory) {
            return null;
        }

        $stats = $inventory->getLanguageStats();
        if ([] === $stats) {
            return null;
        }

        uasort($stats, static fn (array $a, array $b) => ($b['lines'] ?? 0) <=> ($a['lines'] ?? 0));

        return array_key_first($stats);
    }
}
