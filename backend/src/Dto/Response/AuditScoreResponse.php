<?php

declare(strict_types=1);

namespace App\Dto\Response;

use App\Entity\AuditScore;

final class AuditScoreResponse
{
    public string $category;
    public float $score;

    public static function fromEntity(AuditScore $score): self
    {
        $dto = new self();
        $dto->category = $score->getCategory()->value;
        $dto->score = $score->getScore();

        return $dto;
    }
}
