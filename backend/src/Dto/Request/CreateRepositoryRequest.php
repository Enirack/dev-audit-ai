<?php

declare(strict_types=1);

namespace App\Dto\Request;

use Symfony\Component\Validator\Constraints as Assert;

final class CreateRepositoryRequest
{
    #[Assert\NotBlank]
    #[Assert\Regex(
        pattern: '#^https://github\.com/[\w.-]+/[\w.-]+/?$#',
        message: 'The url must be a public GitHub repository URL, e.g. https://github.com/owner/repo',
    )]
    public string $url = '';

    #[Assert\Length(max: 1000)]
    public ?string $description = null;
}
