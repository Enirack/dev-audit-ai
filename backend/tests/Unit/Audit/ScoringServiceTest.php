<?php

declare(strict_types=1);

namespace App\Tests\Unit\Audit;

use App\Audit\ScoringService;
use App\Entity\Enum\FindingCategory;
use App\Entity\Enum\FindingSeverity;
use App\StaticAnalysis\Finding;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ScoringServiceTest extends TestCase
{
    private ScoringService $service;

    protected function setUp(): void
    {
        $this->service = new ScoringService();
    }

    public function testEmptyFindingsYieldPerfectCategoryScore(): void
    {
        self::assertSame(100.0, $this->service->computeCategoryScore([]));
    }

    public function testSingleCriticalFindingImpact(): void
    {
        $finding = $this->makeFinding(FindingSeverity::Critical, 0.9);

        // min(60, 25 * 0.9^0.7) = min(60, 25 * 0.9286...) ≈ 23.2 -> score ≈ 76.8
        self::assertEqualsWithDelta(76.8, $this->service->computeCategoryScore([$finding]), 0.2);
    }

    public function testManyLowSeverityFindingsAreCapped(): void
    {
        $findings = array_map(
            fn () => $this->makeFinding(FindingSeverity::Low, 0.6),
            range(1, 100),
        );

        // sum of confidences = 60; min(12, 1.5 * 60^0.7) = 12 (capped)
        self::assertGreaterThanOrEqual(88.0, $this->service->computeCategoryScore($findings));
    }

    public function testConfidenceScalesThePenalty(): void
    {
        $lowConfidence = $this->service->computeCategoryScore([$this->makeFinding(FindingSeverity::High, 0.3)]);
        $highConfidence = $this->service->computeCategoryScore([$this->makeFinding(FindingSeverity::High, 1.0)]);

        self::assertGreaterThan($highConfidence, $lowConfidence);
    }

    public function testCriticalFindingDominatesOverManyLowSeverityFindings(): void
    {
        $manyLow = array_map(fn () => $this->makeFinding(FindingSeverity::Low, 0.6), range(1, 100));
        $oneCritical = [$this->makeFinding(FindingSeverity::Critical, 0.9)];

        $lowScore = $this->service->computeCategoryScore($manyLow);
        $criticalScore = $this->service->computeCategoryScore($oneCritical);

        self::assertLessThan($lowScore, $criticalScore);
    }

    public function testOverallScoreIsWeightedCombination(): void
    {
        $categoryScores = [
            'security' => 50.7,
            'architecture' => 90.0,
            'maintainability' => 85.0,
            'code_quality' => 78.0,
            'testing' => 40.0,
            'performance' => 95.0,
        ];

        // 50.7*0.30 + 90*0.20 + 85*0.15 + 78*0.15 + 40*0.10 + 95*0.10 = 71.16
        self::assertEqualsWithDelta(71.2, $this->service->computeOverallScore($categoryScores), 0.05);
    }

    public function testOverallScoreForAllPerfectCategoriesIsOneHundred(): void
    {
        $categoryScores = array_fill_keys(array_keys(ScoringService::CATEGORY_WEIGHTS), 100.0);

        self::assertSame(100.0, $this->service->computeOverallScore($categoryScores));
    }

    #[DataProvider('priorityMatrix')]
    public function testPriorityMatrix(FindingSeverity $severity, float $confidence, int $expectedPriority): void
    {
        self::assertSame($expectedPriority, $this->service->computePriority($severity, $confidence));
    }

    /** @return iterable<string, array{FindingSeverity, float, int}> */
    public static function priorityMatrix(): iterable
    {
        yield 'critical, high confidence' => [FindingSeverity::Critical, 1.0, 5];
        yield 'critical, low confidence' => [FindingSeverity::Critical, 0.3, 2];
        yield 'high, high confidence' => [FindingSeverity::High, 1.0, 4];
        yield 'medium, full confidence' => [FindingSeverity::Medium, 1.0, 3];
        yield 'low, full confidence' => [FindingSeverity::Low, 1.0, 2];
        yield 'info, full confidence' => [FindingSeverity::Info, 1.0, 1];
        yield 'info, zero-ish confidence still clamps to 1' => [FindingSeverity::Info, 0.1, 1];
    }

    public function testComputeAllCategoryScoresCoversAllSixCategories(): void
    {
        $scores = $this->service->computeAllCategoryScores([$this->makeFinding(FindingSeverity::High, 0.8, FindingCategory::Testing)]);

        self::assertCount(6, $scores);
        self::assertSame(100.0, $scores['security']);
        self::assertLessThan(100.0, $scores['testing']);
    }

    private function makeFinding(FindingSeverity $severity, float $confidence, FindingCategory $category = FindingCategory::Security): Finding
    {
        return new Finding(
            ruleId: 'test.rule',
            category: $category,
            severity: $severity,
            title: 'Test finding',
            description: 'Test description',
            filePath: null,
            startLine: null,
            endLine: null,
            recommendation: null,
            confidence: $confidence,
            analyzer: 'test',
        );
    }
}
