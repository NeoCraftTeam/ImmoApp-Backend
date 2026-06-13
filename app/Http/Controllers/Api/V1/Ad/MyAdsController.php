<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Ad;

use App\Enums\AdStatus;
use App\Enums\PropertyAttribute;
use App\Http\Resources\AdResource as AdApiResource;
use App\Models\Ad;
use App\Models\City;
use Illuminate\Database\Connection;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Str;

final class MyAdsController
{
    /**
     * List all ads owned by the authenticated user (landlord/agent).
     *
     * @OA\Get(
     *     path="/api/v1/my/ads",
     *     summary="Mes annonces",
     *     description="Retourne toutes les annonces (y compris supprimées) de l'utilisateur authentifié avec filtres et tri.",
     *     tags={"🏠 Annonces"},
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(name="q", in="query", @OA\Schema(type="string")),
     *     @OA\Parameter(name="status", in="query", @OA\Schema(type="string")),
     *     @OA\Parameter(name="type_id", in="query", @OA\Schema(type="string")),
     *     @OA\Parameter(name="city_id", in="query", @OA\Schema(type="string")),
     *
     *     @OA\Response(response=200, description="Liste paginée des annonces"),
     *     @OA\Response(response=401, description="Non authentifié")
     * )
     */
    public function index(): AnonymousResourceCollection
    {
        $userId = auth()->id();
        if (!$userId) {
            abort(401, 'Non authentifié');
        }

        $query = Ad::query()
            ->where('user_id', $userId)
            ->with([
                'quarter:id,name,city_id',
                'quarter.city:id,name',
                'ad_type:id,name',
                'media',
                'user:id,firstname,lastname,avatar,agency_id,city_id',
                'user.agency:id,name,slug,logo',
                'user.media',
                'user.latestTrustScore',
                'agency:id,name,slug,logo',
            ])
            ->withAvg('reviews', 'rating')
            ->withCount('reviews')
            ->withTrashed();

        $q = Str::of(request('q', ''))->trim()->toString();
        if (Str::length($q) >= 2) {
            $pattern = '%'.$q.'%';
            /** @var Connection $connection */
            $connection = $query->getConnection();
            $driver = $connection->getDriverName();
            $likeOp = $driver === 'pgsql' ? 'ilike' : 'like';
            $query->where(function ($builder) use ($pattern, $likeOp): void {
                $builder->where('title', $likeOp, $pattern)
                    ->orWhere('adresse', $likeOp, $pattern)
                    ->orWhere('description', $likeOp, $pattern);
            });
        }

        if ($status = request('status')) {
            $valid = AdStatus::tryFrom($status);
            if ($valid) {
                $query->where('status', $valid);
            }
        }

        if ($typeId = request('type_id')) {
            $query->where('type_id', $typeId);
        } elseif ($typeName = request('type_name')) {
            $query->whereHas('ad_type', fn ($qb) => $qb->where('name', 'ilike', (string) $typeName));
        }

        if ($cityId = request('city_id')) {
            $query->whereHas('quarter', fn ($q) => $q->where('city_id', $cityId));
        } elseif ($cityName = request('city_name')) {
            $cityRow = City::query()->where('name', 'ilike', (string) $cityName)->first();
            if ($cityRow !== null) {
                $query->whereHas('quarter', fn ($q) => $q->where('city_id', $cityRow->id));
            }
        }

        if ($quarterId = request('quarter_id')) {
            $query->where('quarter_id', $quarterId);
        } elseif ($quarterName = request('quarter_name')) {
            $query->whereHas('quarter', fn ($qb) => $qb->where('name', 'ilike', (string) $quarterName));
        }

        // boost_status from the owner-NLP parser. Falls back to the legacy
        // `is_boosted=true` truthy flag for backward compatibility.
        $boostStatus = request('boost_status');
        if ($boostStatus === 'boosted' || request()->boolean('is_boosted')) {
            $query->where('is_boosted', true)->where('boost_expires_at', '>', now());
        } elseif ($boostStatus === 'not_boosted') {
            $query->where(function ($qb): void {
                $qb->where('is_boosted', false)
                    ->orWhereNull('boost_expires_at')
                    ->orWhere('boost_expires_at', '<=', now());
            });
        }

        if (request()->has('is_visible')) {
            $query->where('is_visible', request()->boolean('is_visible'));
        }

        if ($transactionType = request('transaction_type')) {
            if (in_array($transactionType, ['location', 'vente'], true)) {
                $query->where('transaction_type', $transactionType);
            }
        }

        if ($bedrooms = request('bedrooms')) {
            $query->where('bedrooms', '>=', (int) $bedrooms);
        }

        if ($surfaceMin = request('surface_min')) {
            $query->where('surface_area', '>=', (float) $surfaceMin);
        }

        if (request()->boolean('furnished')) {
            $furnishedAttr = PropertyAttribute::Furnished->value;
            $query->where(function ($qb) use ($furnishedAttr): void {
                $qb->where('is_furnished', true)
                    ->orWhereJsonContains('attributes', $furnishedAttr);
            });
        }

        if ($viewsMin = request('views_min')) {
            $query->where('views_count', '>=', (int) $viewsMin);
        }
        if ($viewsMax = request('views_max')) {
            $query->where('views_count', '<=', (int) $viewsMax);
        }

        if ($minPrice = request('price_min')) {
            $query->where('price', '>=', (float) $minPrice);
        }
        if ($maxPrice = request('price_max')) {
            $query->where('price', '<=', (float) $maxPrice);
        }

        $sort = request('sort', 'created_at');
        $order = strtolower((string) request('order', 'desc')) === 'asc' ? 'asc' : 'desc';
        $allowedSort = ['created_at', 'price', 'surface_area', 'title'];
        if (in_array($sort, $allowedSort, true)) {
            $query->orderBy($sort, $order);
        } else {
            $query->latest();
        }

        $ads = $query->paginate(max(1, min(100, (int) request('per_page', 15))));

        return AdApiResource::collection($ads);
    }
}
