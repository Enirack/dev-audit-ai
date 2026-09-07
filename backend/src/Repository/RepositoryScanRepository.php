<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Repository as RepositoryEntity;
use App\Entity\RepositoryScan;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<RepositoryScan>
 */
class RepositoryScanRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RepositoryScan::class);
    }

    /**
     * Fetch-joins audit/inventory rather than relying on findBy(), since
     * RepositoryScanResponse::fromEntity() accesses both for every scan —
     * without the join, each row triggers two extra lazy-load queries.
     *
     * @return RepositoryScan[]
     */
    public function findByRepository(RepositoryEntity $repository): array
    {
        return $this->createQueryBuilder('s')
            ->leftJoin('s.audit', 'a')->addSelect('a')
            ->leftJoin('s.inventory', 'i')->addSelect('i')
            ->andWhere('s.repository = :repository')
            ->setParameter('repository', $repository->getId())
            ->orderBy('s.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
