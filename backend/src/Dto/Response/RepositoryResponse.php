<?php

declare(strict_types=1);

namespace App\Dto\Response;

use App\Entity\Repository;

final class RepositoryResponse
{
    public string $id;
    public string $name;
    public string $url;
    public string $provider;
    public ?string $defaultBranch;
    public ?string $description;
    public string $createdAt;
    public string $updatedAt;

    public static function fromEntity(Repository $repository): self
    {
        $dto = new self();
        $dto->id = (string) $repository->getId();
        $dto->name = $repository->getName();
        $dto->url = $repository->getUrl();
        $dto->provider = $repository->getProvider()->value;
        $dto->defaultBranch = $repository->getDefaultBranch();
        $dto->description = $repository->getDescription();
        $dto->createdAt = $repository->getCreatedAt()->format(DATE_ATOM);
        $dto->updatedAt = $repository->getUpdatedAt()->format(DATE_ATOM);

        return $dto;
    }
}
