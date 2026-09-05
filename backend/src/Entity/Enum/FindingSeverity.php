<?php

declare(strict_types=1);

namespace App\Entity\Enum;

enum FindingSeverity: string
{
    case Info = 'info';
    case Low = 'low';
    case Medium = 'medium';
    case High = 'high';
    case Critical = 'critical';
}
