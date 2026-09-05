<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Enum\FindingCategory;
use App\Repository\AuditScoreRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: AuditScoreRepository::class)]
#[ORM\Table(name: 'audit_scores')]
#[ORM\UniqueConstraint(name: 'uniq_audit_score_category', fields: ['audit', 'category'])]
class AuditScore
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: Audit::class, inversedBy: 'scores')]
    #[ORM\JoinColumn(name: 'audit_id', nullable: false, onDelete: 'CASCADE')]
    private Audit $audit;

    #[ORM\Column(length: 32, enumType: FindingCategory::class)]
    private FindingCategory $category;

    #[ORM\Column]
    private float $score;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct(Audit $audit, FindingCategory $category, float $score)
    {
        $this->id = Uuid::v7();
        $this->audit = $audit;
        $this->category = $category;
        $this->score = $score;
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

    public function getCategory(): FindingCategory
    {
        return $this->category;
    }

    public function getScore(): float
    {
        return $this->score;
    }

    public function setScore(float $score): void
    {
        $this->score = $score;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
