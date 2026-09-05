<?php

declare(strict_types=1);

namespace App\Tests\Unit\StaticAnalysis;

use App\Service\Scanning\RepositoryScanResult;
use App\Service\Scanning\ScannerConfig;
use App\StaticAnalysis\Analyzer\JavaScriptAnalyzer;
use App\StaticAnalysis\Analyzer\PhpAnalyzer;
use App\StaticAnalysis\Analyzer\PythonAnalyzer;
use App\StaticAnalysis\Analyzer\TypeScriptAnalyzer;
use App\StaticAnalysis\RepositoryAnalysisContext;
use App\StaticAnalysis\Rule\Security\DangerousEvalRule;
use App\StaticAnalysis\Rule\Security\HardcodedSecretRule;
use App\StaticAnalysis\Rule\Security\UnsafeCommandExecutionRule;
use App\StaticAnalysis\Rule\Testing\MissingTestDirectoryRule;
use App\StaticAnalysis\StaticAnalysisEngine;
use PHPUnit\Framework\TestCase;

final class StaticAnalysisEngineTest extends TestCase
{
    private string $workspace;

    protected function setUp(): void
    {
        $this->workspace = sys_get_temp_dir().'/static-analysis-engine-test-'.uniqid();
        mkdir($this->workspace.'/src', 0777, true);
        mkdir($this->workspace.'/node_modules', 0777, true);

        file_put_contents($this->workspace.'/src/app.php', "<?php\neval(\$_GET['code']);\n");
        file_put_contents($this->workspace.'/node_modules/vendor.js', "eval('should never be analyzed');\n");
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->workspace);
    }

    public function testEngineFindsPerFileAndRepositoryLevelFindings(): void
    {
        $rules = [new DangerousEvalRule(), new HardcodedSecretRule(), new UnsafeCommandExecutionRule()];
        $scannerConfig = new ScannerConfig(
            ignoredDirectories: ['node_modules', '.git', 'vendor'],
            ignoredFilePatterns: [],
            binaryExtensions: [],
            maxFileSizeBytes: 5242880,
            maxRepositorySizeBytes: 209715200,
            maxFiles: 1000,
        );

        $engine = new StaticAnalysisEngine(
            analyzers: [
                new JavaScriptAnalyzer($rules),
                new TypeScriptAnalyzer($rules),
                new PhpAnalyzer($rules),
                new PythonAnalyzer($rules),
            ],
            repositoryRules: [new MissingTestDirectoryRule()],
            scannerConfig: $scannerConfig,
        );

        $context = new RepositoryAnalysisContext(
            $this->workspace,
            new RepositoryScanResult(2, 2, 100, 0, 0, ['PHP' => ['files' => 1, 'lines' => 2]], ['php' => 1], ['testDirectories' => []]),
        );

        $findings = $engine->analyze($context);
        $ruleIds = array_map(static fn ($f) => $f->ruleId, $findings);

        self::assertContains('security.dangerous-eval', $ruleIds);
        self::assertContains('testing.missing-test-directory', $ruleIds);

        // node_modules must never be analyzed, even though it contains an obvious eval().
        $filePaths = array_filter(array_map(static fn ($f) => $f->filePath, $findings));
        foreach ($filePaths as $filePath) {
            self::assertStringNotContainsString('node_modules', $filePath);
        }
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $items = scandir($dir) ?: [];
        foreach ($items as $item) {
            if ('.' === $item || '..' === $item) {
                continue;
            }
            $path = $dir.'/'.$item;
            is_dir($path) ? $this->removeDirectory($path) : unlink($path);
        }

        rmdir($dir);
    }
}
