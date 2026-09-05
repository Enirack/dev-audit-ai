<?php

declare(strict_types=1);

namespace App\Validator;

use App\Exception\InvalidGitHubUrlException;
use App\Service\GitHub\GitHubUrlParser;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

final class GitHubRepositoryUrlValidator extends ConstraintValidator
{
    public function __construct(private readonly GitHubUrlParser $parser)
    {
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof GitHubRepositoryUrl) {
            throw new UnexpectedTypeException($constraint, GitHubRepositoryUrl::class);
        }

        if (null === $value || '' === $value) {
            return;
        }

        if (!is_string($value)) {
            throw new UnexpectedTypeException($value, 'string');
        }

        try {
            $this->parser->parse($value);
        } catch (InvalidGitHubUrlException) {
            $this->context->buildViolation($constraint->message)->addViolation();
        }
    }
}
