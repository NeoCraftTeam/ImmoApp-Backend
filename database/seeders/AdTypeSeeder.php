<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\AdType;

class AdTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Crée les 8 types définis dans AdTypeFactory si aucun n'existe encore
        if (AdType::count() === 0) {
            \App\Models\AdType::factory()->count(8)->create();
        }
    }
}
