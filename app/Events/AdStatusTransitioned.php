<?php

declare(strict_types=1);

namespace App\Events;

use App\Enums\AdStatus;
use App\Models\Ad;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AdStatusTransitioned
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Ad $ad,
        public AdStatus $oldStatus,
        public AdStatus $newStatus,
    ) {}
}
