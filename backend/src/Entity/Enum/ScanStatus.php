<?php

declare(strict_types=1);

namespace App\Entity\Enum;

enum ScanStatus: string
{
    case Pending = 'pending';
    case Running = 'running';
    case Completed = 'completed';
    case Failed = 'failed';
}
