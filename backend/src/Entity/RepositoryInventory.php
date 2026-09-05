<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\RepositoryInventoryRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * The structured, deterministic output of the Phase 3 repository scanner.
 * One-to-one with a RepositoryScan, following the same convention as Audit.
 */
#[ORM\Entity(repositoryClass: RepositoryInventoryRepository::class)]
#[ORM\Table(name: 'repository_inventories')]
class RepositoryInventory
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    private Uuid $id;

    #[ORM\OneToOne(targetEntity: RepositoryScan::class, inversedBy: 'inventory')]
    #[ORM\JoinColumn(name: 'repository_scan_id', nullable: false, onDelete: 'CASCADE')]
    private RepositoryScan $repositoryScan;

    #[ORM\Column]
    private int $totalFiles;

    #[ORM\Column]
    private int $totalDirectories;

    #[ORM\Column]
    private int $totalSizeBytes;

    #[ORM\Column]
    private int $binaryFileCount;

    #[ORM\Column]
    private int $ignoredFileCount;

    /** @var array<string, array{files: int, lines: int}> */
    #[ORM\Column(type: Types::JSON)]
    private array $languageStats;

    /** @var array<string, int> */
    #[ORM\Column(type: Types::JSON)]
    private array $extensionStats;

    /** @var array<string, mixed> */
    #[ORM\Column(type: Types::JSON)]
    private array $metadata;

    #[ORM\Column]
    private \DateTimeImmutable $generatedAt;

    /**
     * @param array<string, array{files: int, lines: int}> $languageStats
     * @param array<string, int>                            $extensionStats
     * @param array<string, mixed>                           $metadata
     */
    public function __construct(
        RepositoryScan $repositoryScan,
        int $totalFiles,
        int $totalDirectories,
        int $totalSizeBytes,
        int $binaryFileCount,
        int $ignoredFileCount,
        array $languageStats,
        array $extensionStats,
        array $metadata,
    ) {
        $this->id = Uuid::v7();
        $this->repositoryScan = $repositoryScan;
        $this->totalFiles = $totalFiles;
        $this->totalDirectories = $totalDirectories;
        $this->totalSizeBytes = $totalSizeBytes;
        $this->binaryFileCount = $binaryFileCount;
        $this->ignoredFileCount = $ignoredFileCount;
        $this->languageStats = $languageStats;
        $this->extensionStats = $extensionStats;
        $this->metadata = $metadata;
        $this->generatedAt = new \DateTimeImmutable();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getRepositoryScan(): RepositoryScan
    {
        return $this->repositoryScan;
    }

    public function getTotalFiles(): int
    {
        return $this->totalFiles;
    }

    public function getTotalDirectories(): int
    {
        return $this->totalDirectories;
    }

    public function getTotalSizeBytes(): int
    {
        return $this->totalSizeBytes;
    }

    public function getBinaryFileCount(): int
    {
        return $this->binaryFileCount;
    }

    public function getIgnoredFileCount(): int
    {
        return $this->ignoredFileCount;
    }

    /** @return array<string, array{files: int, lines: int}> */
    public function getLanguageStats(): array
    {
        return $this->languageStats;
    }

    /** @return array<string, int> */
    public function getExtensionStats(): array
    {
        return $this->extensionStats;
    }

    /** @return array<string, mixed> */
    public function getMetadata(): array
    {
        return $this->metadata;
    }

    public function getGeneratedAt(): \DateTimeImmutable
    {
        return $this->generatedAt;
    }
}
