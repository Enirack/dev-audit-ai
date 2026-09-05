<?php

declare(strict_types=1);

namespace App\Audit;

use App\Entity\Audit;
use App\Entity\AuditScore;
use App\Entity\Enum\FindingCategory;
use App\Entity\RepositoryScan;
use App\StaticAnalysis\Finding;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Turns a set of deterministic Findings (Phase 4's output) into a persisted
 * Audit: findings, per-category scores, and an overall score. No AI
 * involved — see App\StaticAnalysis for the analysis that produced the
 * findings this consumes.
 */
final class AuditService
{
    public function __construct(
        private readonly ScoringService $scoringService,
        private readonly FindingPersister $findingPersister,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /** @param Finding[] $findings */
    public function createAudit(RepositoryScan $scan, array $findings): Audit
    {
        $audit = new Audit($scan);

        $this->findingPersister->persist($audit, $findings);

        $categoryScores = $this->scoringService->computeAllCategoryScores($findings);
        foreach (FindingCategory::cases() as $category) {
            $audit->addScore(new AuditScore($audit, $category, $categoryScores[$category->value]));
        }

        $audit->setOverallScore($this->scoringService->computeOverallScore($categoryScores));

        $this->entityManager->persist($audit);
        $scan->attachAudit($audit);

        return $audit;
    }
}
