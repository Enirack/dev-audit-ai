<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Enum\FindingCategory;
use App\Entity\Enum\FindingSeverity;
use App\Repository\AuditFindingRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: AuditFindingRepository::class)]
#[ORM\Table(name: 'audit_findings')]
#[ORM\Index(name: 'idx_finding_severity', fields: ['severity'])]
#[ORM\Index(name: 'idx_finding_category', fields: ['category'])]
#[ORM\Index(name: 'idx_finding_rule_id', fields: ['ruleId'])]
#[ORM\Index(name: 'idx_finding_priority', fields: ['priority'])]
class AuditFinding
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: Audit::class, inversedBy: 'findings')]
    #[ORM\JoinColumn(name: 'audit_id', nullable: false, onDelete: 'CASCADE')]
    private Audit $audit;

    #[ORM\Column(length: 128)]
    private string $ruleId;

    #[ORM\Column(length: 32, enumType: FindingCategory::class)]
    private FindingCategory $category;

    #[ORM\Column(length: 16, enumType: FindingSeverity::class)]
    private FindingSeverity $severity;

    #[ORM\Column(length: 255)]
    private string $title;

    #[ORM\Column(type: 'text')]
    private string $description;

    #[ORM\Column(length: 1024, nullable: true)]
    private ?string $filePath = null;

    #[ORM\Column(nullable: true)]
    private ?int $startLine = null;

    #[ORM\Column(nullable: true)]
    private ?int $endLine = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $recommendation = null;

    #[ORM\Column(nullable: true)]
    private ?float $confidence = null;

    #[ORM\Column]
    private int $priority;

    #[ORM\Column(length: 128)]
    private string $source;

    /** @var array<string, mixed>|null */
    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $metadata = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct(
        Audit $audit,
        string $ruleId,
        FindingCategory $category,
        FindingSeverity $severity,
        string $title,
        string $description,
        string $source,
        int $priority,
    ) {
        $this->id = Uuid::v7();
        $this->audit = $audit;
        $this->ruleId = $ruleId;
        $this->category = $category;
        $this->severity = $severity;
        $this->title = $title;
        $this->description = $description;
        $this->source = $source;
        $this->priority = $priority;
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

    public function getRuleId(): string
    {
        return $this->ruleId;
    }

    public function getCategory(): FindingCategory
    {
        return $this->category;
    }

    public function getSeverity(): FindingSeverity
    {
        return $this->severity;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function getFilePath(): ?string
    {
        return $this->filePath;
    }

    public function setFilePath(?string $filePath): void
    {
        $this->filePath = $filePath;
    }

    public function getStartLine(): ?int
    {
        return $this->startLine;
    }

    public function setStartLine(?int $startLine): void
    {
        $this->startLine = $startLine;
    }

    public function getEndLine(): ?int
    {
        return $this->endLine;
    }

    public function setEndLine(?int $endLine): void
    {
        $this->endLine = $endLine;
    }

    public function getRecommendation(): ?string
    {
        return $this->recommendation;
    }

    public function setRecommendation(?string $recommendation): void
    {
        $this->recommendation = $recommendation;
    }

    public function getConfidence(): ?float
    {
        return $this->confidence;
    }

    public function setConfidence(?float $confidence): void
    {
        $this->confidence = $confidence;
    }

    public function getSource(): string
    {
        return $this->source;
    }

    public function getPriority(): int
    {
        return $this->priority;
    }

    /** @return array<string, mixed>|null */
    public function getMetadata(): ?array
    {
        return $this->metadata;
    }

    /** @param array<string, mixed>|null $metadata */
    public function setMetadata(?array $metadata): void
    {
        $this->metadata = $metadata;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
