<?php

declare(strict_types=1);

namespace App\Tests\Unit\StaticAnalysis\Rule\Architecture;

use App\Service\Scanning\RepositoryScanResult;
use App\StaticAnalysis\RepositoryAnalysisContext;
use App\StaticAnalysis\Rule\Architecture\ExcessiveDependenciesRule;
use PHPUnit\Framework\TestCase;

final class ExcessiveDependenciesRuleTest extends TestCase
{
    private ExcessiveDependenciesRule $rule;
    private string $workspace;

    protected function setUp(): void
    {
        $this->rule = new ExcessiveDependenciesRule();
        $this->workspace = sys_get_temp_dir().'/excessive-deps-test-'.uniqid();
        mkdir($this->workspace);
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->workspace.'/*') ?: []);
        @rmdir($this->workspace);
    }

    public function testManyNpmDependenciesAreFlagged(): void
    {
        $dependencies = [];
        for ($i = 0; $i < 90; ++$i) {
            $dependencies["package-{$i}"] = '^1.0.0';
        }
        file_put_contents($this->workspace.'/package.json', json_encode(['dependencies' => $dependencies]));

        $findings = $this->rule->evaluate($this->makeContext());

        self::assertCount(1, $findings);
        self::assertSame(90, $findings[0]->metadata['count']);
    }

    public function testFewNpmDependenciesAreNotFlagged(): void
    {
        file_put_contents($this->workspace.'/package.json', json_encode(['dependencies' => ['left-pad' => '^1.0.0']]));

        self::assertSame([], $this->rule->evaluate($this->makeContext()));
    }

    public function testNoManifestFilesYieldsNoFindings(): void
    {
        self::assertSame([], $this->rule->evaluate($this->makeContext()));
    }

    private function makeContext(): RepositoryAnalysisContext
    {
        $scanResult = new RepositoryScanResult(0, 0, 0, 0, 0, [], [], []);

        return new RepositoryAnalysisContext($this->workspace, $scanResult);
    }
}
