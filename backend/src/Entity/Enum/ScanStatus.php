<?php

declare(strict_types=1);

namespace App\Entity\Enum;

enum ScanStatus: string
{
    case Pending = 'pending';
    case Cloning = 'cloning';
    case Scanning = 'scanning';
    case Completed = 'completed';
    case Failed = 'failed';
}
