<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Enums\UserType;
use App\Models\Agency;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AgencyService
{
    /**
     * Promote a user to an Agency Owner.
     */
    public function promoteToAgency(User $user, string $agencyName): Agency
    {
        return DB::transaction(function () use ($user, $agencyName) {
            $agency = Agency::create([
                'name' => $agencyName,
                'slug' => Str::slug($agencyName),
                'owner_id' => $user->id,
            ]);

            $user->update([
                'role' => UserRole::AGENT,
                'type' => UserType::AGENCY,
                'agency_id' => $agency->id,
            ]);

            return $agency;
        });
    }

    /**
     * Promote a user to a private Bailleur (Individual Agent).
     */
    public function promoteToBailleur(User $user): Agency
    {
        return DB::transaction(function () use ($user) {
            // Un bailleur a aussi besoin d'une "Agency" technique pour Filament Multi-tenancy
            $agency = Agency::create([
                'name' => 'Portefeuille de '.$user->firstname.' '.$user->lastname,
                'slug' => Str::slug('bailleur-'.$user->id),
                'owner_id' => $user->id,
            ]);

            $user->update([
                'role' => UserRole::AGENT,
                'type' => UserType::INDIVIDUAL,
                'agency_id' => $agency->id,
            ]);

            return $agency;
        });
    }
}
