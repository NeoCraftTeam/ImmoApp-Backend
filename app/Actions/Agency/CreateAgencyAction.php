<?php

namespace App\Actions\Agency;

use App\Models\Agency;

class CreateAgencyAction
{
    public function handle(array $data): Agency
    {
        return Agency::create($data);
    }
}
