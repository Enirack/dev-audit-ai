<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Thin client for the Python/FastAPI analysis engine, reached over HTTP
 * across the Docker network (service name "ai-engine" in compose).
 */
final class AiEngineClient
{
    private const REQUEST_TIMEOUT_SECONDS = 30.0;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $baseUrl,
        private readonly ?string $internalSecret,
    ) {
    }

    /**
     * @return array{status: string, service?: string}|null null when the engine is unreachable
     */
    public function checkHealth(): ?array
    {
        try {
            $response = $this->httpClient->request('GET', $this->url('/health'), [
                'timeout' => 3.0,
            ]);

            /** @var array{status: string, service?: string} $data */
            $data = $response->toArray();

            return $data;
        } catch (ExceptionInterface) {
            return null;
        }
    }

    /** @param array<string, mixed> $payload @return array<string, mixed> */
    public function explainFinding(array $payload): array
    {
        return $this->post('/ai/findings/explain', $payload);
    }

    /** @param array<string, mixed> $payload @return array<string, mixed> */
    public function generateExecutiveSummary(array $payload): array
    {
        return $this->post('/ai/summary', $payload);
    }

    /** @param array<string, mixed> $payload @return array<string, mixed> */
    public function explainArchitecture(array $payload): array
    {
        return $this->post('/ai/architecture', $payload);
    }

    /** @param array<string, mixed> $payload @return array<string, mixed> */
    public function generateRefactoringPlan(array $payload): array
    {
        return $this->post('/ai/refactoring-plan', $payload);
    }

    /** @param array<string, mixed> $payload @return array<string, mixed> */
    public function chat(array $payload): array
    {
        return $this->post('/ai/chat', $payload);
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function post(string $path, array $payload): array
    {
        $headers = [];
        if (null !== $this->internalSecret && '' !== $this->internalSecret) {
            $headers['X-Internal-Auth'] = $this->internalSecret;
        }

        try {
            $response = $this->httpClient->request('POST', $this->url($path), [
                'json' => $payload,
                'headers' => $headers,
                'timeout' => self::REQUEST_TIMEOUT_SECONDS,
            ]);

            $status = $response->getStatusCode();
            if ($status >= 400) {
                throw new AiEngineException(sprintf('AI engine returned HTTP %d for %s.', $status, $path));
            }

            /** @var array<string, mixed> $data */
            $data = $response->toArray(false);

            return $data;
        } catch (ExceptionInterface $exception) {
            throw new AiEngineException(sprintf('AI engine request to %s failed: %s', $path, $exception->getMessage()), 0, $exception);
        }
    }

    private function url(string $path): string
    {
        return rtrim($this->baseUrl, '/').$path;
    }
}
