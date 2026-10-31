<?php

declare(strict_types=1);

namespace App\Enums;

enum OtpResult: string
{
    case Valid = 'valid';
    case Invalid = 'invalid';
    case Locked = 'locked';
}
