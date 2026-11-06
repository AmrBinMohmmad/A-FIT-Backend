<?php

declare(strict_types=1);

namespace App\Enums;

/** Where a food's numbers came from — shown to the user so they can judge trust. */
enum FoodSource: string
{
    /** The curated seed (USDA reference values for generic items). */
    case Catalog = 'catalog';
    /** Imported from Open Food Facts (brand products, ODbL — attribution in the app). */
    case OpenFoodFacts = 'open_food_facts';
    /** Added by the user from a package label; visible only to that user. */
    case User = 'user';
}
