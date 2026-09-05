<?php

declare(strict_types=1);

namespace App\Service\Scanning;

/**
 * Detects well-known metadata files by name/relative path only.
 * Never opens file contents — operates purely on the paths handed to it.
 */
final class RepositoryMetadataDetector
{
    /** @var array<string, mixed> */
    private array $metadata = [
        'packageJson' => false,
        'packageLockJson' => false,
        'composerJson' => false,
        'composerLock' => false,
        'requirementsTxt' => false,
        'pyprojectToml' => false,
        'pipfile' => false,
        'dockerfile' => false,
        'dockerCompose' => false,
        'readme' => false,
        'ciConfig' => false,
        'envFilePresent' => false,
    ];

    /** @var string[] */
    private array $testDirectories = [];

    public function observe(string $relativePath, bool $isDirectory): void
    {
        $filename = basename($relativePath);
        $lowerFilename = strtolower($filename);

        match (true) {
            'package.json' === $filename => $this->metadata['packageJson'] = true,
            'package-lock.json' === $filename => $this->metadata['packageLockJson'] = true,
            'composer.json' === $filename => $this->metadata['composerJson'] = true,
            'composer.lock' === $filename => $this->metadata['composerLock'] = true,
            'requirements.txt' === $filename => $this->metadata['requirementsTxt'] = true,
            'pyproject.toml' === $filename => $this->metadata['pyprojectToml'] = true,
            'Pipfile' === $filename => $this->metadata['pipfile'] = true,
            'Dockerfile' === $filename => $this->metadata['dockerfile'] = true,
            (bool) preg_match('/^(docker-)?compose\.ya?ml$/i', $filename) => $this->metadata['dockerCompose'] = true,
            str_starts_with($lowerFilename, 'readme') => $this->metadata['readme'] = true,
            $this->isCiConfig($relativePath, $filename) => $this->metadata['ciConfig'] = true,
            default => null,
        };

        if (str_starts_with($filename, '.env')) {
            $this->metadata['envFilePresent'] = true;
        }

        if ($isDirectory && $this->isTestDirectoryName($lowerFilename)) {
            $this->testDirectories[] = $relativePath;
        }
    }

    /** @return array<string, mixed> */
    public function getMetadata(): array
    {
        return $this->metadata;
    }

    /** @return string[] */
    public function getTestDirectories(): array
    {
        return $this->testDirectories;
    }

    private function isCiConfig(string $relativePath, string $filename): bool
    {
        if (str_starts_with($relativePath, '.github/workflows/') && (str_ends_with($filename, '.yml') || str_ends_with($filename, '.yaml'))) {
            return true;
        }

        return in_array($filename, ['.gitlab-ci.yml', 'Jenkinsfile', 'azure-pipelines.yml'], true)
            || '.circleci/config.yml' === $relativePath;
    }

    private function isTestDirectoryName(string $lowerName): bool
    {
        return in_array($lowerName, ['tests', 'test', '__tests__', 'spec'], true);
    }
}
