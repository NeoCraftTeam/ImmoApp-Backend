<?php

namespace Database\Seeders;

use App\Models\AdType;
use Illuminate\Database\Seeder;

class AdTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Crée les 8 types définis dans AdTypeFactory si aucun n'existe encore
        if (AdType::count() === 0) {
            AdType::factory()->count(8)->create();
        }
    }
}
