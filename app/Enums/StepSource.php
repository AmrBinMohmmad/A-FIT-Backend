<?php

declare(strict_types=1);

namespace App\Enums;

enum StepSource: string
{
    case HealthKit = 'healthkit';
    case HealthConnect = 'health_connect';
    case Manual = 'manual';
}
