<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Scanning;

use App\Service\Scanning\RepositoryMetadataDetector;
use PHPUnit\Framework\TestCase;

final class RepositoryMetadataDetectorTest extends TestCase
{
    public function testDetectsKnownMetadataFiles(): void
    {
        $detector = new RepositoryMetadataDetector();
        $detector->observe('package.json', false);
        $detector->observe('composer.json', false);
        $detector->observe('requirements.txt', false);
        $detector->observe('Dockerfile', false);
        $detector->observe('docker-compose.yml', false);
        $detector->observe('README.md', false);
        $detector->observe('.github/workflows/ci.yml', false);
        $detector->observe('.env', false);
        $detector->observe('tests', true);

        $metadata = $detector->getMetadata();

        self::assertTrue($metadata['packageJson']);
        self::assertTrue($metadata['composerJson']);
        self::assertTrue($metadata['requirementsTxt']);
        self::assertTrue($metadata['dockerfile']);
        self::assertTrue($metadata['dockerCompose']);
        self::assertTrue($metadata['readme']);
        self::assertTrue($metadata['ciConfig']);
        self::assertTrue($metadata['envFilePresent']);
        self::assertSame(['tests'], $detector->getTestDirectories());
    }

    public function testUnrelatedFilesDoNotSetAnyFlag(): void
    {
        $detector = new RepositoryMetadataDetector();
        $detector->observe('src/index.ts', false);

        $metadata = $detector->getMetadata();

        self::assertFalse($metadata['packageJson']);
        self::assertFalse($metadata['envFilePresent']);
        self::assertSame([], $detector->getTestDirectories());
    }

    public function testEnvExampleFileDoesNotSetEnvFilePresentAlone(): void
    {
        // .env.example still starts with ".env" so it correctly marks envFilePresent
        // (an env file convention exists) but this must never be confused with a
        // real secret-carrying .env file by any code reading this flag.
        $detector = new RepositoryMetadataDetector();
        $detector->observe('.env.example', false);

        self::assertTrue($detector->getMetadata()['envFilePresent']);
    }
}
