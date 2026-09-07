<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Enum\AiInsightType;
use App\Repository\AiInsightRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * A cached AI-generated insight for an audit (executive summary, architecture
 * explanation, refactoring plan) or, when $finding is set, for a single
 * finding. Caching means a given (audit, type[, finding]) combination only
 * calls the ai-engine once unless explicitly regenerated — audits are
 * immutable once created, so a cached insight never goes stale on its own.
 */
#[ORM\Entity(repositoryClass: AiInsightRepository::class)]
#[ORM\Table(name: 'ai_insights')]
#[ORM\Index(name: 'idx_ai_insight_audit_type', fields: ['audit', 'type'])]
class AiInsight
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: Audit::class)]
    #[ORM\JoinColumn(name: 'audit_id', nullable: false, onDelete: 'CASCADE')]
    private Audit $audit;

    #[ORM\ManyToOne(targetEntity: AuditFinding::class)]
    #[ORM\JoinColumn(name: 'finding_id', nullable: true, onDelete: 'CASCADE')]
    private ?AuditFinding $finding = null;

    #[ORM\Column(length: 32, enumType: AiInsightType::class)]
    private AiInsightType $type;

    /** @var array<string, mixed> */
    #[ORM\Column(type: Types::JSON)]
    private array $content;

    #[ORM\Column(length: 64)]
    private string $provider;

    #[ORM\Column]
    private \DateTimeImmutable $generatedAt;

    /** @param array<string, mixed> $content */
    public function __construct(
        Audit $audit,
        AiInsightType $type,
        array $content,
        string $provider,
        ?AuditFinding $finding = null,
    ) {
        $this->id = Uuid::v7();
        $this->audit = $audit;
        $this->type = $type;
        $this->content = $content;
        $this->provider = $provider;
        $this->finding = $finding;
        $this->generatedAt = new \DateTimeImmutable();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getAudit(): Audit
    {
        return $this->audit;
    }

    public function getFinding(): ?AuditFinding
    {
        return $this->finding;
    }

    public function getType(): AiInsightType
    {
        return $this->type;
    }

    /** @return array<string, mixed> */
    public function getContent(): array
    {
        return $this->content;
    }

    public function getProvider(): string
    {
        return $this->provider;
    }

    public function getGeneratedAt(): \DateTimeImmutable
    {
        return $this->generatedAt;
    }
}
