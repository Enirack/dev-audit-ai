<?php

declare(strict_types=1);

namespace App\Service\GitHub;

use App\Exception\InvalidGitHubUrlException;

/**
 * Parses and strictly validates a public GitHub repository URL.
 *
 * Pure/side-effect-free: does not perform any network call. Never trust a
 * URL that fails to parse here to reach any HTTP client or the filesystem.
 */
final class GitHubUrlParser
{
    private const OWNER_PATTERN = '/^[A-Za-z0-9](?:[A-Za-z0-9-]{0,38})$/';
    private const REPO_PATTERN = '/^[A-Za-z0-9._-]{1,100}$/';

    public function parse(string $url): GitHubRepositoryReference
    {
        $url = trim($url);

        $parts = parse_url($url);
        if (false === $parts || !isset($parts['scheme'], $parts['host'], $parts['path'])) {
            throw new InvalidGitHubUrlException('malformed_url', 'The URL could not be parsed.');
        }

        if ('https' !== $parts['scheme']) {
            throw new InvalidGitHubUrlException('invalid_scheme', 'The URL must use https://.');
        }

        if (isset($parts['user']) || isset($parts['pass'])) {
            throw new InvalidGitHubUrlException('credentials_in_url', 'The URL must not contain credentials.');
        }

        if (isset($parts['port'])) {
            throw new InvalidGitHubUrlException('unexpected_port', 'The URL must not specify a port.');
        }

        if (strtolower($parts['host']) !== 'github.com') {
            throw new InvalidGitHubUrlException('invalid_host', 'Only https://github.com repository URLs are accepted.');
        }

        $decodedPath = rawurldecode($parts['path']);
        if (str_contains($decodedPath, '..')
            || str_contains($decodedPath, "\0")
            || str_contains($decodedPath, '\\')
            || str_contains($parts['path'], '%2f') || str_contains($parts['path'], '%2F')
        ) {
            throw new InvalidGitHubUrlException('path_traversal', 'The URL path is not a valid repository path.');
        }

        $path = rtrim($decodedPath, '/');
        if (str_ends_with($path, '.git')) {
            $path = substr($path, 0, -4);
        }

        $segments = array_values(array_filter(explode('/', $path), static fn (string $s): bool => '' !== $s));
        if (2 !== count($segments)) {
            throw new InvalidGitHubUrlException('malformed_path', 'The URL must point directly to a repository: https://github.com/owner/repo.');
        }

        [$owner, $repo] = $segments;

        if (1 !== preg_match(self::OWNER_PATTERN, $owner)) {
            throw new InvalidGitHubUrlException('invalid_owner', 'The repository owner segment is not a valid GitHub username or organization.');
        }

        if (1 !== preg_match(self::REPO_PATTERN, $repo) || '.' === $repo || '..' === $repo) {
            throw new InvalidGitHubUrlException('invalid_repo', 'The repository name segment is not valid.');
        }

        return new GitHubRepositoryReference($owner, $repo);
    }
}
