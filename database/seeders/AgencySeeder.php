<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Enums\UserType;
use App\Models\Agency;
use App\Models\User;
use Illuminate\Database\Seeder;

class AgencySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get all agents who are of type 'agency'
        $agents = User::where('role', UserRole::AGENT)
            ->where('type', UserType::AGENCY)
            ->get();

        $agents->each(function ($agent) {
            // Check if agencies already exist for this owner to avoid duplication on re-runs (optional but good practice)
            if (Agency::where('owner_id', $agent->id)->doesntExist()) {
                Agency::factory()->count(10)->create([
                    'owner_id' => $agent->id,
                    'name' => 'Agence ' . $agent->lastname,
                ]);
            }
        });
    }
}
