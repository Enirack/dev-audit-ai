<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\AuditRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: AuditRepository::class)]
#[ORM\Table(name: 'audits')]
class Audit
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    private Uuid $id;

    #[ORM\OneToOne(targetEntity: RepositoryScan::class, inversedBy: 'audit')]
    #[ORM\JoinColumn(name: 'repository_scan_id', nullable: false, onDelete: 'CASCADE')]
    private RepositoryScan $repositoryScan;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $summary = null;

    #[ORM\Column(nullable: true)]
    private ?float $overallScore = null;

    #[ORM\Column]
    private \DateTimeImmutable $generatedAt;

    /** @var Collection<int, AuditFinding> */
    #[ORM\OneToMany(targetEntity: AuditFinding::class, mappedBy: 'audit', orphanRemoval: true, cascade: ['persist'])]
    private Collection $findings;

    /** @var Collection<int, AuditScore> */
    #[ORM\OneToMany(targetEntity: AuditScore::class, mappedBy: 'audit', orphanRemoval: true, cascade: ['persist'])]
    private Collection $scores;

    public function __construct(RepositoryScan $repositoryScan)
    {
        $this->id = Uuid::v7();
        $this->repositoryScan = $repositoryScan;
        $this->generatedAt = new \DateTimeImmutable();
        $this->findings = new ArrayCollection();
        $this->scores = new ArrayCollection();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getRepositoryScan(): RepositoryScan
    {
        return $this->repositoryScan;
    }

    public function getSummary(): ?string
    {
        return $this->summary;
    }

    public function setSummary(?string $summary): void
    {
        $this->summary = $summary;
    }

    public function getOverallScore(): ?float
    {
        return $this->overallScore;
    }

    public function setOverallScore(float $overallScore): void
    {
        $this->overallScore = $overallScore;
    }

    public function getGeneratedAt(): \DateTimeImmutable
    {
        return $this->generatedAt;
    }

    /** @return Collection<int, AuditFinding> */
    public function getFindings(): Collection
    {
        return $this->findings;
    }

    public function addFinding(AuditFinding $finding): void
    {
        if (!$this->findings->contains($finding)) {
            $this->findings->add($finding);
        }
    }

    /** @return Collection<int, AuditScore> */
    public function getScores(): Collection
    {
        return $this->scores;
    }

    public function addScore(AuditScore $score): void
    {
        if (!$this->scores->contains($score)) {
            $this->scores->add($score);
        }
    }
}
