<?php

declare(strict_types=1);

namespace App\Entity\Enum;

enum FindingCategory: string
{
    case Architecture = 'architecture';
    case Security = 'security';
    case Maintainability = 'maintainability';
    case Performance = 'performance';
    case Testing = 'testing';
    case CodeQuality = 'code_quality';
}
