<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Service\AiEngineClient;
use Doctrine\DBAL\Connection;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final class HealthController
{
    public function __construct(
        private readonly Connection $connection,
        private readonly AiEngineClient $aiEngineClient,
    ) {
    }

    #[Route('/api/health', name: 'api_health', methods: ['GET'])]
    public function __invoke(): JsonResponse
    {
        $databaseOk = $this->isDatabaseHealthy();
        $aiEngine = $this->aiEngineClient->checkHealth();

        $status = $databaseOk && null !== $aiEngine ? 'ok' : 'degraded';

        return new JsonResponse([
            'status' => $status,
            'service' => 'devaudit-backend',
            'dependencies' => [
                'database' => $databaseOk ? 'ok' : 'unreachable',
                'ai_engine' => null !== $aiEngine ? 'ok' : 'unreachable',
            ],
        ], $status === 'ok' ? 200 : 503);
    }

    private function isDatabaseHealthy(): bool
    {
        try {
            $this->connection->executeQuery('SELECT 1');

            return true;
        } catch (\Throwable) {
            return false;
        }
    }
}
