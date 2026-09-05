<?php

declare(strict_types=1);

namespace App\Dto\Response;

use App\Entity\User;

final class UserResponse
{
    public string $id;
    public string $email;
    public string $createdAt;

    public static function fromEntity(User $user): self
    {
        $dto = new self();
        $dto->id = (string) $user->getId();
        $dto->email = $user->getEmail();
        $dto->createdAt = $user->getCreatedAt()->format(DATE_ATOM);

        return $dto;
    }
}
