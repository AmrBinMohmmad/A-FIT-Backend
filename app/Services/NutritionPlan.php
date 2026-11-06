<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ActivityLevel;
use App\Enums\Gender;
use App\Enums\GoalType;

/**
 * The authoritative daily-target calculation. The mobile client
 * keeps a preview mirror of this exact arithmetic for the onboarding screen and
 * labels it "Mirrors app/Services/NutritionPlan.php"; the stored targets always
 * come from here.
 *
 * Mifflin-St Jeor BMR × activity factor = TDEE. The goal adjustment is PACED, not a
 * flat number: we aim to reach the target weight in about TARGET_WEEKS, with the weekly
 * pace clamped to a safe band (lose 0.25–0.5 kg/week, gain 0.15–0.3 kg/week), and
 * 7 700 kcal per kilogram. So 1 kg to gain means roughly +170 kcal/day, not +350; 10 kg
 * to lose means about −550/day. A floor of 1200 (female) / 1500 (male) kcal; calories
 * rounded to 10; protein 1.5–1.9 g per kg of target weight clamped to 20–30 % of
 * calories; fat 28 %; carbs the remainder.
 */
final class NutritionPlan
{
    public const KCAL_PER_KG = 7700;

    public const TARGET_WEEKS = 12;

    public const LOSE_PACE_KG_PER_WEEK = [0.25, 0.5];

    public const GAIN_PACE_KG_PER_WEEK = [0.15, 0.3];

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
        $goal = match (true) {
            $diff < -0.5 => GoalType::Lose,
            $diff > 0.5 => GoalType::Gain,
            default => GoalType::Maintain,
        };
        $adjustment = self::dailyAdjustment($goal, abs($diff));

        $floor = $gender === Gender::Female ? 1200 : 1500;
        $calories = max($floor, (int) (round(($tdee + $adjustment) / 10) * 10));

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

    /** Signed kcal/day for the goal, from a weekly pace clamped to the safe band. */
    public static function dailyAdjustment(GoalType $goal, float $kgToGo): int
    {
        if ($goal === GoalType::Maintain) {
            return 0;
        }

        [$min, $max] = $goal === GoalType::Lose ? self::LOSE_PACE_KG_PER_WEEK : self::GAIN_PACE_KG_PER_WEEK;
        $pace = min($max, max($min, $kgToGo / self::TARGET_WEEKS));
        $daily = (int) (round($pace * self::KCAL_PER_KG / 7 / 10) * 10);

        return $goal === GoalType::Lose ? -$daily : $daily;
    }
}
