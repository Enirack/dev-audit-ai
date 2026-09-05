<?php

declare(strict_types=1);

namespace App\Dto\Response;

final class PaginatedFindingsResponse
{
    /** @param AuditFindingResponse[] $data */
    public function __construct(
        public array $data,
        public int $page,
        public int $perPage,
        public int $total,
        public int $totalPages,
    ) {
    }
}
