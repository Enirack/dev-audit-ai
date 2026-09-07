<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\AiChatMessageRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: AiChatMessageRepository::class)]
#[ORM\Table(name: 'ai_chat_messages')]
#[ORM\Index(name: 'idx_ai_chat_audit_created', fields: ['audit', 'createdAt'])]
class AiChatMessage
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: Audit::class)]
    #[ORM\JoinColumn(name: 'audit_id', nullable: false, onDelete: 'CASCADE')]
    private Audit $audit;

    #[ORM\Column(length: 16)]
    private string $role;

    #[ORM\Column(type: 'text')]
    private string $content;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct(Audit $audit, string $role, string $content)
    {
        $this->id = Uuid::v7();
        $this->audit = $audit;
        $this->role = $role;
        $this->content = $content;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getAudit(): Audit
    {
        return $this->audit;
    }

    public function getRole(): string
    {
        return $this->role;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
