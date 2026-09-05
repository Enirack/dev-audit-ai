<?php

declare(strict_types=1);

namespace App\Service\GitHub;

final readonly class GitHubRepositoryReference
{
    public function __construct(
        public string $owner,
        public string $repo,
    ) {
    }

    public function fullName(): string
    {
        return "{$this->owner}/{$this->repo}";
    }

    public function canonicalUrl(): string
    {
        return "https://github.com/{$this->owner}/{$this->repo}";
    }
}
