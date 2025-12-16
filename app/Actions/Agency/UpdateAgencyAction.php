<?php

namespace App\Actions\Agency;

use App\Models\Agency;

class UpdateAgencyAction
{
    public function handle(Agency $agency, array $data): Agency
    {
        $agency->update($data);

        return $agency->fresh();
    }
}
