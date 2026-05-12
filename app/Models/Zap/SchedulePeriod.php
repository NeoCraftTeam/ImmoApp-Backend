<?php

declare(strict_types=1);

namespace App\Models\Zap;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Zap\Models\SchedulePeriod as BaseSchedulePeriod;

class SchedulePeriod extends BaseSchedulePeriod
{
    use HasUuids;
}
