<?php

declare(strict_types=1);

namespace App\Validator;

use Symfony\Component\Validator\Constraint;

#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_PARAMETER)]
final class GitHubRepositoryUrl extends Constraint
{
    public string $message = 'The url must be a public GitHub repository URL, e.g. https://github.com/owner/repo.';
}
