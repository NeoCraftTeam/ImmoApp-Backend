<?php

declare(strict_types=1);

namespace App\Models\Zap;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Zap\Models\Schedule as BaseSchedule;

class Schedule extends BaseSchedule
{
    use HasUuids;
}
