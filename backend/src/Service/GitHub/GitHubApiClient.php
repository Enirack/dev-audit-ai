<?php

declare(strict_types=1);

namespace App\Service\GitHub;

use App\Exception\GitHubApiException;
use App\Exception\GitHubRepositoryNotFoundException;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface as HttpClientExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Thin wrapper over GitHub's public REST API. Never used to retrieve
 * repository source code — only metadata (existence, default branch,
 * commit sha). Content retrieval goes through GitHubIngestionService.
 */
final class GitHubApiClient
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
        private readonly string $apiBaseUrl,
        private readonly string $githubToken = '',
    ) {
    }

    /**
     * @return array{defaultBranch: string, description: ?string, private: bool}
     */
    public function getRepositoryInfo(GitHubRepositoryReference $reference): array
    {
        $response = $this->request('GET', "/repos/{$reference->owner}/{$reference->repo}");

        $data = $response;

        if (true === ($data['private'] ?? false)) {
            throw new GitHubRepositoryNotFoundException('Repository not found or is private.');
        }

        return [
            'defaultBranch' => (string) ($data['default_branch'] ?? 'main'),
            'description' => is_string($data['description'] ?? null) ? $data['description'] : null,
            'private' => (bool) ($data['private'] ?? false),
        ];
    }

    public function resolveCommitSha(GitHubRepositoryReference $reference, string $branch): ?string
    {
        try {
            $data = $this->request('GET', "/repos/{$reference->owner}/{$reference->repo}/commits/{$branch}");

            return is_string($data['sha'] ?? null) ? $data['sha'] : null;
        } catch (\Throwable $e) {
            $this->logger->warning('Failed to resolve GitHub commit sha, continuing without it.', ['exception' => $e]);

            return null;
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function request(string $method, string $path): array
    {
        $headers = ['Accept' => 'application/vnd.github+json'];
        if ('' !== $this->githubToken) {
            $headers['Authorization'] = "Bearer {$this->githubToken}";
        }

        try {
            $response = $this->httpClient->request($method, rtrim($this->apiBaseUrl, '/').$path, [
                'headers' => $headers,
                'timeout' => 10,
            ]);

            $status = $response->getStatusCode();

            if (404 === $status) {
                throw new GitHubRepositoryNotFoundException('Repository not found or is private.');
            }

            if (403 === $status || 429 === $status) {
                throw new GitHubApiException('GitHub API rate limit exceeded.');
            }

            if ($status >= 400) {
                throw new GitHubApiException("GitHub API returned an unexpected status code: {$status}.");
            }

            /** @var array<string, mixed> $data */
            $data = $response->toArray();

            return $data;
        } catch (HttpClientExceptionInterface $e) {
            $this->logger->error('GitHub API request failed.', ['exception' => $e]);

            throw new GitHubApiException('Unable to reach the GitHub API.', previous: $e);
        }
    }
}
