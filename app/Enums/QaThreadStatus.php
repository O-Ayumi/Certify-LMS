<?php

declare(strict_types=1);

namespace App\Enums;

enum QaThreadStatus: string
{
    case Open = 'open';
    case Resolved = 'resolved';
}
