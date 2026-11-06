<?php

declare(strict_types=1);

namespace App\Enums;

/** Grocery aisles for browsing and filtering the food catalog. Mirrored in the mobile app. */
enum FoodCategory: string
{
    case Eggs = 'eggs';
    case Dairy = 'dairy';
    case Bread = 'bread';
    case Grains = 'grains';
    case Legumes = 'legumes';
    case Meat = 'meat';
    case Poultry = 'poultry';
    case Seafood = 'seafood';
    case Vegetables = 'vegetables';
    case Fruits = 'fruits';
    case NutsSeeds = 'nuts_seeds';
    case FatsOils = 'fats_oils';
    case Beverages = 'beverages';
    case Snacks = 'snacks';
    case Sweets = 'sweets';
    case Dishes = 'dishes';
    case Other = 'other';
}
