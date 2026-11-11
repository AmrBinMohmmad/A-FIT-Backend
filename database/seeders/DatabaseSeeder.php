<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /** The food catalog is reference data and seeds everywhere (idempotent); nothing else does. */
    public function run(): void
    {
        $this->call(FoodCatalogSeeder::class);
    }
}
