<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\AiChatMessage;
use App\Entity\Audit;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AiChatMessage>
 */
class AiChatMessageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AiChatMessage::class);
    }

    /** @return AiChatMessage[] */
    public function findConversation(Audit $audit): array
    {
        return $this->findBy(['audit' => $audit], ['createdAt' => 'ASC']);
    }
}
