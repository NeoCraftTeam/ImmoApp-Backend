<?php

declare(strict_types=1);

namespace App\Actions\Agency;

use App\Models\Agency;

class DeleteAgencyAction
{
    public function handle(Agency $agency): void
    {
        $agency->delete();
    }
}
