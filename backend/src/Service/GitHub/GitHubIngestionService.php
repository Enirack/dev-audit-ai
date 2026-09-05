<?php

declare(strict_types=1);

namespace App\Service\GitHub;

use App\Exception\IngestionSizeLimitExceededException;
use App\Exception\IngestionTimeoutException;
use App\Exception\UnsafeArchiveException;
use Psr\Log\LoggerInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface as HttpClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Downloads a public GitHub repository as a source-only tarball snapshot
 * (GitHub's codeload service) into an isolated, size- and time-bounded
 * temporary workspace.
 *
 * Deliberately does NOT shell out to `git clone`: a tarball has no `.git`
 * directory, no hooks, no submodules and no credential helpers to worry
 * about, which removes an entire class of command-injection / arbitrary
 * code execution risk rather than merely mitigating it.
 *
 * Repository code is never executed at any point in this class.
 */
final class GitHubIngestionService
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly Filesystem $filesystem,
        private readonly LoggerInterface $logger,
        private readonly string $codeloadBaseUrl,
        private readonly string $workspaceRootDir,
        private readonly int $maxArchiveBytes,
        private readonly int $maxRepositoryBytes,
        private readonly int $timeoutSeconds,
    ) {
    }

    public function ingest(GitHubRepositoryReference $reference, string $branch, string $scanId): IngestedWorkspace
    {
        $workspaceDir = rtrim($this->workspaceRootDir, '/\\').'/'.$scanId;
        $this->filesystem->mkdir($workspaceDir, 0700);

        try {
            $archivePath = $workspaceDir.'/archive.tar.gz';
            $this->downloadArchive($reference, $branch, $archivePath);
            $extractedTo = $workspaceDir.'/source';
            $this->filesystem->mkdir($extractedTo, 0700);
            $this->extractSafely($archivePath, $extractedTo);
            $this->filesystem->remove($archivePath);

            return new IngestedWorkspace($workspaceDir, $this->resolveExtractedRoot($extractedTo));
        } catch (\Throwable $e) {
            $this->filesystem->remove($workspaceDir);
            throw $e;
        }
    }

    public function cleanup(IngestedWorkspace $workspace): void
    {
        $this->filesystem->remove($workspace->workspaceDir);
    }

    private function downloadArchive(GitHubRepositoryReference $reference, string $branch, string $destination): void
    {
        $url = rtrim($this->codeloadBaseUrl, '/')
            ."/{$reference->owner}/{$reference->repo}/tar.gz/refs/heads/".rawurlencode($branch);

        try {
            $response = $this->httpClient->request('GET', $url, [
                'timeout' => 10,
                'max_duration' => $this->timeoutSeconds,
            ]);

            $headers = $response->getHeaders();
            $declaredLength = isset($headers['content-length'][0]) ? (int) $headers['content-length'][0] : null;
            if (null !== $declaredLength && $declaredLength > $this->maxArchiveBytes) {
                throw new IngestionSizeLimitExceededException('Repository archive exceeds the maximum allowed download size.');
            }

            $handle = fopen($destination, 'wb');
            if (false === $handle) {
                throw new \RuntimeException('Unable to open workspace file for writing.');
            }

            $written = 0;

            try {
                foreach ($this->httpClient->stream($response, $this->timeoutSeconds) as $chunk) {
                    if ($chunk->isTimeout()) {
                        throw new IngestionTimeoutException('Repository download timed out.');
                    }

                    $content = $chunk->getContent();
                    $written += strlen($content);
                    if ($written > $this->maxArchiveBytes) {
                        throw new IngestionSizeLimitExceededException('Repository archive exceeds the maximum allowed download size.');
                    }

                    fwrite($handle, $content);
                }
            } finally {
                fclose($handle);
            }
        } catch (TransportExceptionInterface $e) {
            $this->logger->warning('GitHub tarball download failed.', ['exception' => $e]);

            throw new IngestionTimeoutException('Unable to download the repository archive.', previous: $e);
        } catch (HttpClientExceptionInterface $e) {
            throw new \RuntimeException('Unexpected error while downloading the repository archive.', previous: $e);
        }
    }

    private function extractSafely(string $archivePath, string $destination): void
    {
        try {
            $phar = new \PharData($archivePath);
        } catch (\Exception $e) {
            throw new UnsafeArchiveException('The downloaded archive could not be read.', previous: $e);
        }

        $totalSize = 0;
        $iterator = new \RecursiveIteratorIterator($phar, \RecursiveIteratorIterator::SELF_FIRST);

        foreach ($iterator as $fileInfo) {
            /** @var \PharFileInfo $fileInfo */
            $relative = $iterator->getSubPathName();

            if (str_contains($relative, '..') || str_starts_with($relative, '/') || str_contains($relative, "\0")) {
                throw new UnsafeArchiveException('The archive contains an unsafe file path and was rejected.');
            }

            if ($fileInfo->isLink()) {
                throw new UnsafeArchiveException('The archive contains a symbolic link and was rejected.');
            }

            if ($fileInfo->isFile()) {
                $totalSize += $fileInfo->getSize();
                if ($totalSize > $this->maxRepositoryBytes) {
                    throw new IngestionSizeLimitExceededException('Repository exceeds the maximum allowed uncompressed size.');
                }
            }
        }

        try {
            $phar->extractTo($destination, null, true);
        } catch (\Exception $e) {
            throw new UnsafeArchiveException('Failed to safely extract the repository archive.', previous: $e);
        }
    }

    private function resolveExtractedRoot(string $extractedTo): string
    {
        $entries = array_values(array_diff(scandir($extractedTo) ?: [], ['.', '..']));

        // GitHub tarballs wrap all content in a single "{repo}-{ref}/" directory.
        if (1 === count($entries) && is_dir($extractedTo.'/'.$entries[0])) {
            return $extractedTo.'/'.$entries[0];
        }

        throw new UnsafeArchiveException('The repository archive did not have the expected single top-level directory.');
    }
}
