<?php

declare(strict_types=1);

namespace App\Enums;

enum ActivityLevel: string
{
    case Sedentary = 'sedentary';
    case Light = 'light';
    case Moderate = 'moderate';
    case Active = 'active';
    case ExtraActive = 'extra_active';

    /** TDEE multiplier (Mifflin-St Jeor activity factors). */
    public function multiplier(): float
    {
        return match ($this) {
            self::Sedentary => 1.2,
            self::Light => 1.375,
            self::Moderate => 1.55,
            self::Active => 1.725,
            self::ExtraActive => 1.9,
        };
    }
}
