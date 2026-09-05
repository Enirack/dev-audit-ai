<?php

declare(strict_types=1);

namespace App\Audit;

use App\Entity\Audit;
use App\Entity\AuditFinding;
use App\StaticAnalysis\Finding;

final class FindingPersister
{
    public function __construct(private readonly ScoringService $scoringService)
    {
    }

    /** @param Finding[] $findings */
    public function persist(Audit $audit, array $findings): void
    {
        foreach ($findings as $finding) {
            $entity = new AuditFinding(
                $audit,
                $finding->ruleId,
                $finding->category,
                $finding->severity,
                $finding->title,
                $finding->description,
                $finding->analyzer,
                $this->scoringService->computePriority($finding->severity, $finding->confidence),
            );
            $entity->setFilePath($finding->filePath);
            $entity->setStartLine($finding->startLine);
            $entity->setEndLine($finding->endLine);
            $entity->setRecommendation($finding->recommendation);
            $entity->setConfidence($finding->confidence);
            if ([] !== $finding->metadata) {
                $entity->setMetadata($finding->metadata);
            }

            $audit->addFinding($entity);
        }
    }
}
