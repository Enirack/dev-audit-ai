<?php

declare(strict_types=1);

namespace App\Entity\Enum;

enum AiInsightType: string
{
    case FindingExplanation = 'finding_explanation';
    case ExecutiveSummary = 'executive_summary';
    case ArchitectureExplanation = 'architecture_explanation';
    case RefactoringPlan = 'refactoring_plan';
}
