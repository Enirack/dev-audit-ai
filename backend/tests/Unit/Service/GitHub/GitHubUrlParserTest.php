<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\GitHub;

use App\Exception\InvalidGitHubUrlException;
use App\Service\GitHub\GitHubUrlParser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class GitHubUrlParserTest extends TestCase
{
    private GitHubUrlParser $parser;

    protected function setUp(): void
    {
        $this->parser = new GitHubUrlParser();
    }

    public function testValidUrlIsParsed(): void
    {
        $reference = $this->parser->parse('https://github.com/octocat/Hello-World');

        self::assertSame('octocat', $reference->owner);
        self::assertSame('Hello-World', $reference->repo);
        self::assertSame('octocat/Hello-World', $reference->fullName());
        self::assertSame('https://github.com/octocat/Hello-World', $reference->canonicalUrl());
    }

    public function testTrailingSlashAndDotGitSuffixAreStripped(): void
    {
        $reference = $this->parser->parse('https://github.com/octocat/Hello-World.git');
        self::assertSame('Hello-World', $reference->repo);

        $reference = $this->parser->parse('https://github.com/octocat/Hello-World/');
        self::assertSame('Hello-World', $reference->repo);
    }

    #[DataProvider('invalidUrls')]
    public function testInvalidUrlsAreRejected(string $url, string $expectedReasonCode): void
    {
        try {
            $this->parser->parse($url);
            self::fail("Expected {$url} to be rejected.");
        } catch (InvalidGitHubUrlException $e) {
            self::assertSame($expectedReasonCode, $e->reasonCode);
        }
    }

    /** @return iterable<string, array{string, string}> */
    public static function invalidUrls(): iterable
    {
        yield 'non-github host' => ['https://gitlab.com/octocat/Hello-World', 'invalid_host'];
        yield 'lookalike host' => ['https://github.com.evil.com/octocat/Hello-World', 'invalid_host'];
        yield 'subdomain host' => ['https://raw.githubusercontent.com/octocat/Hello-World', 'invalid_host'];
        yield 'http scheme' => ['http://github.com/octocat/Hello-World', 'invalid_scheme'];
        yield 'ssh scheme' => ['ssh://github.com/octocat/Hello-World', 'invalid_scheme'];
        yield 'git protocol' => ['git://github.com/octocat/Hello-World.git', 'invalid_scheme'];
        yield 'credentials in url' => ['https://user:pass@github.com/octocat/Hello-World', 'credentials_in_url'];
        yield 'explicit port' => ['https://github.com:8443/octocat/Hello-World', 'unexpected_port'];
        yield 'path traversal dots' => ['https://github.com/octocat/../../etc/passwd', 'path_traversal'];
        yield 'encoded path traversal' => ['https://github.com/octocat/%2e%2e/passwd', 'path_traversal'];
        yield 'encoded slash' => ['https://github.com/octocat%2Fevil/Hello-World', 'path_traversal'];
        yield 'too few segments' => ['https://github.com/octocat', 'malformed_path'];
        yield 'too many segments' => ['https://github.com/octocat/Hello-World/tree/main', 'malformed_path'];
        yield 'root only' => ['https://github.com/', 'malformed_path'];
        yield 'not a url at all' => ['not-a-url', 'malformed_url'];
        yield 'invalid owner characters' => ['https://github.com/-octocat/Hello-World', 'invalid_owner'];
        yield 'invalid repo characters' => ['https://github.com/octocat/Hello World', 'invalid_repo'];
    }
}
