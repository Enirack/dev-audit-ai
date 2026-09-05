<?php

declare(strict_types=1);

namespace App\Dto\Response;

use App\Entity\RepositoryScan;

final class RepositoryScanResponse
{
    public string $id;
    public string $repositoryId;
    public string $status;
    public ?string $commitSha;
    public ?string $startedAt;
    public ?string $finishedAt;
    public ?string $errorMessage;
    public string $createdAt;

    public static function fromEntity(RepositoryScan $scan): self
    {
        $dto = new self();
        $dto->id = (string) $scan->getId();
        $dto->repositoryId = (string) $scan->getRepository()->getId();
        $dto->status = $scan->getStatus()->value;
        $dto->commitSha = $scan->getCommitSha();
        $dto->startedAt = $scan->getStartedAt()?->format(DATE_ATOM);
        $dto->finishedAt = $scan->getFinishedAt()?->format(DATE_ATOM);
        $dto->errorMessage = $scan->getErrorMessage();
        $dto->createdAt = $scan->getCreatedAt()->format(DATE_ATOM);

        return $dto;
    }
}
