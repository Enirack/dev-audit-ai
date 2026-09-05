<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Enum\ScanStatus;
use App\Repository\RepositoryScanRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: RepositoryScanRepository::class)]
#[ORM\Table(name: 'repository_scans')]
class RepositoryScan
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: Repository::class, inversedBy: 'scans')]
    #[ORM\JoinColumn(name: 'repository_id', nullable: false, onDelete: 'CASCADE')]
    private Repository $repository;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'triggered_by_id', nullable: true, onDelete: 'SET NULL')]
    private ?User $triggeredBy = null;

    #[ORM\Column(length: 32, enumType: ScanStatus::class)]
    private ScanStatus $status;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $commitSha = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $startedAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $finishedAt = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $errorMessage = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\OneToOne(targetEntity: Audit::class, mappedBy: 'repositoryScan', cascade: ['persist', 'remove'])]
    private ?Audit $audit = null;

    #[ORM\OneToOne(targetEntity: RepositoryInventory::class, mappedBy: 'repositoryScan', cascade: ['persist', 'remove'])]
    private ?RepositoryInventory $inventory = null;

    public function __construct(Repository $repository, ?User $triggeredBy = null)
    {
        $this->id = Uuid::v7();
        $this->repository = $repository;
        $this->triggeredBy = $triggeredBy;
        $this->status = ScanStatus::Pending;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getRepository(): Repository
    {
        return $this->repository;
    }

    public function getTriggeredBy(): ?User
    {
        return $this->triggeredBy;
    }

    public function getStatus(): ScanStatus
    {
        return $this->status;
    }

    public function markCloning(): void
    {
        $this->status = ScanStatus::Cloning;
        $this->startedAt = new \DateTimeImmutable();
    }

    public function markScanning(): void
    {
        $this->status = ScanStatus::Scanning;
    }

    public function markCompleted(): void
    {
        $this->status = ScanStatus::Completed;
        $this->finishedAt = new \DateTimeImmutable();
    }

    public function markFailed(string $errorMessage): void
    {
        $this->status = ScanStatus::Failed;
        $this->finishedAt = new \DateTimeImmutable();
        $this->errorMessage = $errorMessage;
    }

    public function getCommitSha(): ?string
    {
        return $this->commitSha;
    }

    public function setCommitSha(?string $commitSha): void
    {
        $this->commitSha = $commitSha;
    }

    public function getStartedAt(): ?\DateTimeImmutable
    {
        return $this->startedAt;
    }

    public function getFinishedAt(): ?\DateTimeImmutable
    {
        return $this->finishedAt;
    }

    public function getErrorMessage(): ?string
    {
        return $this->errorMessage;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getAudit(): ?Audit
    {
        return $this->audit;
    }

    public function getInventory(): ?RepositoryInventory
    {
        return $this->inventory;
    }

    /**
     * Doctrine does not sync the inverse side of a one-to-one association in
     * memory just because the owning side was constructed with a reference
     * back to this entity — call this so the current request's in-memory
     * object graph is consistent without needing a re-fetch.
     */
    public function attachInventory(RepositoryInventory $inventory): void
    {
        $this->inventory = $inventory;
    }
}
