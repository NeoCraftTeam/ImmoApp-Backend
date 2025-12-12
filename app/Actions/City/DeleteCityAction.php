<?php

namespace App\Actions\City;

use App\Models\City;

class DeleteCityAction
{
    public function handle(City $city): void
    {
        // Vérifier les dépendances avant suppression (ex: utilisateurs liés)
        if ($city->users()->exists()) {
            abort(409, 'Impossible de supprimer cette ville car il contient des utilisateurs.');
        }

        $city->delete();
    }
}
