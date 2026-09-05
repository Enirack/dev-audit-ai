<?php

declare(strict_types=1);

namespace App\Dto\Response;

use App\Entity\AuditFinding;

final class AuditFindingResponse
{
    public string $id;
    public string $ruleId;
    public string $category;
    public string $severity;
    public int $priority;
    public ?float $confidence;
    public string $title;
    public string $description;
    public ?string $filePath;
    public ?int $startLine;
    public ?int $endLine;
    public ?string $recommendation;
    public string $analyzer;

    /** @var array<string, mixed>|null */
    public ?array $metadata;

    public string $createdAt;

    public static function fromEntity(AuditFinding $finding): self
    {
        $dto = new self();
        $dto->id = (string) $finding->getId();
        $dto->ruleId = $finding->getRuleId();
        $dto->category = $finding->getCategory()->value;
        $dto->severity = $finding->getSeverity()->value;
        $dto->priority = $finding->getPriority();
        $dto->confidence = $finding->getConfidence();
        $dto->title = $finding->getTitle();
        $dto->description = $finding->getDescription();
        $dto->filePath = $finding->getFilePath();
        $dto->startLine = $finding->getStartLine();
        $dto->endLine = $finding->getEndLine();
        $dto->recommendation = $finding->getRecommendation();
        $dto->analyzer = $finding->getSource();
        $dto->metadata = $finding->getMetadata();
        $dto->createdAt = $finding->getCreatedAt()->format(DATE_ATOM);

        return $dto;
    }
}
