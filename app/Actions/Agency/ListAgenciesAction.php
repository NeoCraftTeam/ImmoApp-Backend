<?php

declare(strict_types=1);

namespace App\Actions\Agency;

use App\Http\Resources\AgencyResource;
use App\Models\Agency;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ListAgenciesAction
{
    public function handle(): AnonymousResourceCollection
    {
        $agencies = Agency::with('users')->paginate(config('pagination.default', 10));

        return AgencyResource::collection($agencies);
    }
}
