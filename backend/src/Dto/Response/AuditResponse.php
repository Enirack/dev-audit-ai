<?php

declare(strict_types=1);

namespace App\Dto\Response;

use App\Entity\Audit;
use App\Entity\Enum\FindingSeverity;

final class AuditResponse
{
    public string $id;
    public string $repositoryScanId;
    public string $repositoryId;
    public string $repositoryName;
    public ?string $summary;
    public ?float $overallScore;
    public string $generatedAt;

    /** @var array<string, int> */
    public array $severityDistribution;

    public int $findingsCount;

    public static function fromEntity(Audit $audit): self
    {
        $dto = new self();
        $dto->id = (string) $audit->getId();
        $dto->repositoryScanId = (string) $audit->getRepositoryScan()->getId();
        $dto->repositoryId = (string) $audit->getRepositoryScan()->getRepository()->getId();
        $dto->repositoryName = $audit->getRepositoryScan()->getRepository()->getName();
        $dto->summary = $audit->getSummary();
        $dto->overallScore = $audit->getOverallScore();
        $dto->generatedAt = $audit->getGeneratedAt()->format(DATE_ATOM);

        $distribution = array_fill_keys(array_map(static fn (FindingSeverity $s) => $s->value, FindingSeverity::cases()), 0);
        foreach ($audit->getFindings() as $finding) {
            ++$distribution[$finding->getSeverity()->value];
        }
        $dto->severityDistribution = $distribution;
        $dto->findingsCount = count($audit->getFindings());

        return $dto;
    }
}
