<?php

declare(strict_types=1);

namespace App\Service\GitHub;

final readonly class IngestedWorkspace
{
    public function __construct(
        public string $workspaceDir,
        public string $root,
    ) {
    }
}
