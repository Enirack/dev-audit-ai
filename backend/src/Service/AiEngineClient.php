<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Thin client for the Python/FastAPI analysis engine, reached over HTTP
 * across the Docker network (service name "ai-engine" in compose).
 */
final class AiEngineClient
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $baseUrl,
    ) {
    }

    /**
     * @return array{status: string, service?: string}|null null when the engine is unreachable
     */
    public function checkHealth(): ?array
    {
        try {
            $response = $this->httpClient->request('GET', rtrim($this->baseUrl, '/') . '/health', [
                'timeout' => 3.0,
            ]);

            /** @var array{status: string, service?: string} $data */
            $data = $response->toArray();

            return $data;
        } catch (TransportExceptionInterface) {
            return null;
        }
    }
}
