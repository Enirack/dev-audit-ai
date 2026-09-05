<?php

declare(strict_types=1);

namespace App\Dto\Response;

use App\Entity\RepositoryInventory;

final class RepositoryInventoryResponse
{
    public int $totalFiles;
    public int $totalDirectories;
    public int $totalSizeBytes;
    public int $binaryFileCount;
    public int $ignoredFileCount;

    /** @var array<string, array{files: int, lines: int}> */
    public array $languageStats;

    /** @var array<string, int> */
    public array $extensionStats;

    /** @var array<string, mixed> */
    public array $metadata;

    public string $generatedAt;

    public static function fromEntity(RepositoryInventory $inventory): self
    {
        $dto = new self();
        $dto->totalFiles = $inventory->getTotalFiles();
        $dto->totalDirectories = $inventory->getTotalDirectories();
        $dto->totalSizeBytes = $inventory->getTotalSizeBytes();
        $dto->binaryFileCount = $inventory->getBinaryFileCount();
        $dto->ignoredFileCount = $inventory->getIgnoredFileCount();
        $dto->languageStats = $inventory->getLanguageStats();
        $dto->extensionStats = $inventory->getExtensionStats();
        $dto->metadata = $inventory->getMetadata();
        $dto->generatedAt = $inventory->getGeneratedAt()->format(DATE_ATOM);

        return $dto;
    }
}
