<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ActivityLevel;
use App\Enums\Gender;
use App\Enums\GoalType;

/**
 * The authoritative daily-target calculation (Rakaya Tier 1.1). The mobile client
 * keeps a preview mirror of this exact arithmetic for the onboarding screen and
 * labels it "Mirrors app/Services/NutritionPlan.php"; the stored targets always
 * come from here.
 *
 * Mifflin-St Jeor BMR × activity factor = TDEE; goal adjusts by −450 / 0 / +350
 * kcal; a floor of 1200 (female) / 1500 (male) kcal; protein 1.5–1.9 g per kg of
 * target weight clamped to 20–30 % of calories; fat 28 %; carbs the remainder.
 */
final class NutritionPlan
{
    /**
     * @return array{
     *   bmr: int, tdee: int, goal_type: GoalType, daily_calories: int,
     *   daily_protein: int, daily_carbs: int, daily_fat: int
     * }
     */
    public static function compute(
        Gender $gender,
        int $age,
        int $heightCm,
        int $currentWeightKg,
        int $targetWeightKg,
        ActivityLevel $activity,
    ): array {
        $bmr = 10 * $currentWeightKg + 6.25 * $heightCm - 5 * $age + ($gender === Gender::Male ? 5 : -161);
        $bmr = (int) round($bmr);

        $tdee = (int) round($bmr * $activity->multiplier());

        $diff = $targetWeightKg - $currentWeightKg;
        [$goal, $adjustment] = match (true) {
            $diff < -0.5 => [GoalType::Lose, -450],
            $diff > 0.5 => [GoalType::Gain, 350],
            default => [GoalType::Maintain, 0],
        };

        $floor = $gender === Gender::Female ? 1200 : 1500;
        $calories = max($floor, (int) round($tdee + $adjustment));

        $proteinPerKg = ($gender === Gender::Female ? 1.5 : 1.7)
            + (in_array($activity, [ActivityLevel::Active, ActivityLevel::ExtraActive], true) ? 0.2 : 0.0);
        $protein = (int) round($targetWeightKg * $proteinPerKg);
        $protein = max((int) round($calories * 0.20 / 4), min((int) round($calories * 0.30 / 4), $protein));

        $fat = (int) round($calories * 0.28 / 9);
        $carbs = (int) round(max(0, $calories - ($protein * 4 + $fat * 9)) / 4);

        return [
            'bmr' => $bmr,
            'tdee' => $tdee,
            'goal_type' => $goal,
            'daily_calories' => $calories,
            'daily_protein' => $protein,
            'daily_carbs' => $carbs,
            'daily_fat' => $fat,
        ];
    }
}
