<?php

declare(strict_types=1);

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Filters queries to only return records belonging to the authenticated user.
 *
 * Used by the Bailleur panel to ensure each landlord only sees their own data.
 */
final class LandlordScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        if (auth()->check()) {
            $builder->where($model->getTable().'.user_id', auth()->id());
        }
    }
}
