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

    /** @return RepositoryScan[] */
    public function findByRepository(RepositoryEntity $repository): array
    {
        return $this->findBy(['repository' => $repository], ['createdAt' => 'DESC']);
    }
}
