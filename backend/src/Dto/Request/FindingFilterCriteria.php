<?php

declare(strict_types=1);

namespace App\Dto\Request;

use App\Entity\Enum\FindingCategory;
use App\Entity\Enum\FindingSeverity;
use Symfony\Component\HttpFoundation\Request;

final readonly class FindingFilterCriteria
{
    public function __construct(
        public ?FindingCategory $category = null,
        public ?FindingSeverity $severity = null,
        public ?string $filePathContains = null,
        public ?string $ruleId = null,
        public int $page = 1,
        public int $perPage = 25,
    ) {
    }

    public static function fromRequest(Request $request): self
    {
        $category = $request->query->get('category');
        $severity = $request->query->get('severity');

        return new self(
            category: is_string($category) ? FindingCategory::tryFrom($category) : null,
            severity: is_string($severity) ? FindingSeverity::tryFrom($severity) : null,
            filePathContains: $request->query->get('file'),
            ruleId: $request->query->get('rule'),
            page: max(1, $request->query->getInt('page', 1)),
            perPage: min(100, max(1, $request->query->getInt('perPage', 25))),
        );
    }
}
