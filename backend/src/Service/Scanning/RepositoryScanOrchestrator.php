<?php

declare(strict_types=1);

namespace App\Service\Scanning;

use App\Audit\AuditService;
use App\Entity\RepositoryInventory;
use App\Entity\RepositoryScan;
use App\Exception\GitHubApiException;
use App\Exception\GitHubRepositoryNotFoundException;
use App\Exception\IngestionSizeLimitExceededException;
use App\Exception\IngestionTimeoutException;
use App\Exception\UnsafeArchiveException;
use App\Service\GitHub\GitHubApiClient;
use App\Service\GitHub\GitHubIngestionService;
use App\Service\GitHub\GitHubUrlParser;
use App\StaticAnalysis\RepositoryAnalysisContext;
use App\StaticAnalysis\StaticAnalysisEngine;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Drives a RepositoryScan through its full lifecycle:
 * pending -> cloning -> scanning -> completed/failed, producing an
 * inventory (Phase 3) and an audit with findings and scores (Phases 4-5)
 * along the way, all while the ingested workspace still exists (it is
 * always cleaned up afterward, per Phase 2's requirement).
 */
final class RepositoryScanOrchestrator
{
    public function __construct(
        private readonly GitHubUrlParser $urlParser,
        private readonly GitHubApiClient $apiClient,
        private readonly GitHubIngestionService $ingestionService,
        private readonly RepositoryScanner $repositoryScanner,
        private readonly StaticAnalysisEngine $staticAnalysisEngine,
        private readonly AuditService $auditService,
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

                $result = $this->repositoryScanner->scan($workspace->root);
                $inventory = new RepositoryInventory(
                    $scan,
                    $result->totalFiles,
                    $result->totalDirectories,
                    $result->totalSizeBytes,
                    $result->binaryFileCount,
                    $result->ignoredFileCount,
                    $result->languageStats,
                    $result->extensionStats,
                    $result->metadata,
                );
                $this->entityManager->persist($inventory);
                $scan->attachInventory($inventory);

                $context = new RepositoryAnalysisContext($workspace->root, $result);
                $findings = $this->staticAnalysisEngine->analyze($context);
                $this->auditService->createAudit($scan, $findings);

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
