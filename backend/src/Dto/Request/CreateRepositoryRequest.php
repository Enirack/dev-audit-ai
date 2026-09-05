<?php

declare(strict_types=1);

namespace App\Dto\Request;

use App\Validator\GitHubRepositoryUrl;
use Symfony\Component\Validator\Constraints as Assert;

final class CreateRepositoryRequest
{
    #[Assert\NotBlank]
    #[GitHubRepositoryUrl]
    public string $url = '';

    #[Assert\Length(max: 1000)]
    public ?string $description = null;
}
