<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\AiInsight;
use App\Entity\Audit;
use App\Entity\AuditFinding;
use App\Entity\Enum\AiInsightType;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AiInsight>
 */
class AiInsightRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AiInsight::class);
    }

    public function findCachedAuditInsight(Audit $audit, AiInsightType $type): ?AiInsight
    {
        return $this->findOneBy(['audit' => $audit, 'type' => $type, 'finding' => null]);
    }

    public function findCachedFindingExplanation(AuditFinding $finding): ?AiInsight
    {
        return $this->findOneBy(['finding' => $finding, 'type' => AiInsightType::FindingExplanation]);
    }
}
