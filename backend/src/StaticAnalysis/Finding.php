<?php

declare(strict_types=1);

namespace App\StaticAnalysis;

use App\Entity\Enum\FindingCategory;
use App\Entity\Enum\FindingSeverity;

/**
 * Deterministic analysis output. Immutable value object — not a Doctrine
 * entity, so the static analysis engine can be tested and used without a
 * database. Persistence into AuditFinding is a separate concern (Phase 5).
 */
final readonly class Finding
{
    /**
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        public string $ruleId,
        public FindingCategory $category,
        public FindingSeverity $severity,
        public string $title,
        public string $description,
        public ?string $filePath,
        public ?int $startLine,
        public ?int $endLine,
        public ?string $recommendation,
        public float $confidence,
        public string $analyzer,
        public array $metadata = [],
    ) {
    }
}
