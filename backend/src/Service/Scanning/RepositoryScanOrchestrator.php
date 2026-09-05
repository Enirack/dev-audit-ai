<?php

declare(strict_types=1);

namespace App\Service\Scanning;

use App\Entity\RepositoryScan;
use App\Exception\GitHubApiException;
use App\Exception\GitHubRepositoryNotFoundException;
use App\Exception\IngestionSizeLimitExceededException;
use App\Exception\IngestionTimeoutException;
use App\Exception\UnsafeArchiveException;
use App\Service\GitHub\GitHubApiClient;
use App\Service\GitHub\GitHubIngestionService;
use App\Service\GitHub\GitHubUrlParser;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Drives a RepositoryScan through its lifecycle: pending -> cloning -> completed/failed.
 *
 * Phase 2 only performs ingestion (cloning); the "scanning" status is set but
 * not yet followed by an actual scan — that lands in Phase 3.
 */
final class RepositoryScanOrchestrator
{
    public function __construct(
        private readonly GitHubUrlParser $urlParser,
        private readonly GitHubApiClient $apiClient,
        private readonly GitHubIngestionService $ingestionService,
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function run(RepositoryScan $scan): void
    {
        $repository = $scan->getRepository();

        $scan->markCloning();
        $this->entityManager->flush();

        try {
            $reference = $this->urlParser->parse($repository->getUrl());
            $branch = $repository->getDefaultBranch() ?? 'main';

            $workspace = $this->ingestionService->ingest($reference, $branch, (string) $scan->getId());

            try {
                $scan->setCommitSha($this->apiClient->resolveCommitSha($reference, $branch));

                $scan->markScanning();
                $this->entityManager->flush();

                // Phase 3 will scan $workspace->root here and persist a RepositoryInventory.

                $scan->markCompleted();
            } finally {
                $this->ingestionService->cleanup($workspace);
            }
        } catch (GitHubRepositoryNotFoundException) {
            $scan->markFailed('Repository not found or is private.');
        } catch (IngestionSizeLimitExceededException) {
            $scan->markFailed('Repository exceeds the maximum allowed size.');
        } catch (IngestionTimeoutException) {
            $scan->markFailed('Repository ingestion timed out.');
        } catch (UnsafeArchiveException) {
            $scan->markFailed('Repository archive contained unsafe entries and was rejected.');
        } catch (GitHubApiException $e) {
            $this->logger->error('GitHub API error during scan.', ['exception' => $e]);
            $scan->markFailed('Unable to reach GitHub. Please try again later.');
        } catch (\Throwable $e) {
            $this->logger->error('Unexpected error during repository scan.', ['exception' => $e]);
            $scan->markFailed('An unexpected error occurred during ingestion.');
        }

        $this->entityManager->flush();
    }
}
