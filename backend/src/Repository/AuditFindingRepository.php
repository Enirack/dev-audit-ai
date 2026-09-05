<?php

declare(strict_types=1);

namespace App\Repository;

use App\Dto\Request\FindingFilterCriteria;
use App\Entity\Audit;
use App\Entity\AuditFinding;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AuditFinding>
 */
class AuditFindingRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AuditFinding::class);
    }

    /**
     * @return array{items: AuditFinding[], total: int}
     */
    public function findByAuditFiltered(Audit $audit, FindingFilterCriteria $criteria): array
    {
        $qb = $this->createQueryBuilder('f')
            ->andWhere('f.audit = :audit')
            ->setParameter('audit', $audit->getId())
            ->orderBy('f.priority', 'DESC')
            ->addOrderBy('f.confidence', 'DESC');

        if (null !== $criteria->category) {
            $qb->andWhere('f.category = :category')->setParameter('category', $criteria->category);
        }

        if (null !== $criteria->severity) {
            $qb->andWhere('f.severity = :severity')->setParameter('severity', $criteria->severity);
        }

        if (null !== $criteria->filePathContains && '' !== $criteria->filePathContains) {
            $qb->andWhere('f.filePath LIKE :filePath')->setParameter('filePath', '%'.$criteria->filePathContains.'%');
        }

        if (null !== $criteria->ruleId && '' !== $criteria->ruleId) {
            $qb->andWhere('f.ruleId = :ruleId')->setParameter('ruleId', $criteria->ruleId);
        }

        $qb->setFirstResult(($criteria->page - 1) * $criteria->perPage)
            ->setMaxResults($criteria->perPage);

        $paginator = new Paginator($qb);

        return [
            'items' => iterator_to_array($paginator->getIterator()),
            'total' => count($paginator),
        ];
    }
}
