<?php

declare(strict_types=1);

namespace App\Services\Ad;

use App\DTOs\AdFeedResult;
use App\Http\Requests\AdRequest;
use App\Models\Ad;
use App\Models\AdType;
use App\Models\User;
use App\Services\Ai\RecommendationEngine;
use Illuminate\Support\Facades\Cache;

/**
 * Assembles the public cursor feed: base query + filters/sort, guest first-page
 * caching, personalised re-ranking, sponsored/organic distribution, and the
 * cached approximate total.
 *
 * Extracted from AdController::feed() so the controller only wires HTTP
 * (authorization + resource rendering) while the ranking/caching orchestration
 * lives here alongside the other Ad services.
 */
final readonly class AdFeedService
{
    public function __construct(
        private RecommendationEngine $engine,
        private AdFeedRankingService $feedRanker,
    ) {}

    public function build(AdRequest $request): AdFeedResult
    {
        $perPage = min(max((int) $request->integer('per_page', config('pagination.per_page', 15)), 1), 50);
        $type = filled($request->input('type')) ? (string) $request->input('type') : null;
        $sort = filled($request->input('sort')) ? (string) $request->input('sort') : 'newest';

        $requestExcludeIds = [];
        if ($rawExcludeIds = $request->input('exclude_ids')) {
            $requestExcludeIds = array_values(array_filter(array_map(strval(...), (array) $rawExcludeIds)));
        }

        $isFirstPageGuest = !auth()->check()
            && !$request->filled('cursor')
            && !$request->filled('exclude_ids')
            && $type === null
            && $sort === 'newest';

        // Cursor pagination uses `$perPage` consistently so:
        //   1. `meta.per_page` matches the user-facing limit;
        //   2. The cursor advances by `$perPage` items per page — when total
        //      inventory is smaller than a multiple of `$perPage`, the
        //      remaining rows still ship on subsequent pages.
        // `AdFeedRankingService::distribute()` runs on whatever lands in the
        // page and degrades to best-effort tier filling when inventory is
        // thin. A wider candidate pool for the slot template would have to be
        // assembled out-of-band (e.g. fetched separately and woven into the
        // paginator), not by inflating cursorPaginate's page size.
        $build = function () use ($perPage, $type, $sort, $requestExcludeIds) {
            $query = Ad::query()->forPublicListing();

            if ($requestExcludeIds !== []) {
                $query->whereNotIn('id', $requestExcludeIds);
            }

            if ($type !== null) {
                $query->whereHas('ad_type', fn ($q) => $q->where('name', 'ilike', "%{$type}%"));
            }

            $ordered = match ($sort) {
                'price_asc' => $query->orderBy('price')->orderByDesc('id'),
                'price_desc' => $query->orderByDesc('price')->orderByDesc('id'),
                default => $query->orderBySponsorship(),
            };

            return $ordered->cursorPaginate($perPage);
        };

        $paginator = $isFirstPageGuest
            ? Cache::remember("ads:feed:guest:first:pp={$perPage}", 300, $build)
            : $build();

        // Two-stage re-ranking for authenticated users.
        // Only applied when using the default sort (explicit price sorts take priority
        // over personalization — the user chose that order intentionally).
        if (auth()->check() && $sort === 'newest') {
            /** @var User $authUser */
            $authUser = auth()->user();
            $profile = $this->engine->getUserProfile($authUser);

            if ($profile !== null) {
                $reranked = $this->engine->scoreCandidates(
                    $paginator->getCollection(),
                    $profile,
                );
                $paginator->setCollection($reranked);
            }
        }

        // Sponsored-feed distribution. La cursor-paginate ne renvoie que
        // `$perPage` lignes — si elles sont toutes sponsorisées (car
        // `orderBySponsorship` les met en tête), `distribute()` n'a aucun
        // organique à insérer aux positions 4/8/9 du slot template, et
        // produit une page 100 % sponsorisée. Pour corriger ça sans
        // casser le cursor (qui doit avancer par `$perPage`), on enrichit
        // la liste de candidats **uniquement sur la première page** avec
        // un échantillon organique séparé. Les pages suivantes restent
        // strictement basées sur la fenêtre cursor.
        if ($sort === 'newest') {
            $candidates = $paginator->getCollection();

            if (!$request->filled('cursor')) {
                // Exclure à la fois les candidats déjà présents ET les
                // `exclude_ids` de la requête : sinon une annonce écartée
                // par l'appelant (déjà vue côté client) serait re-pêchée
                // ici comme organique et réinjectée dans le feed.
                $excludeIds = array_values(array_unique(array_merge(
                    $candidates->pluck('id')->all(),
                    $requestExcludeIds,
                )));

                $organicBoost = Ad::query()->forPublicListing()
                    ->where('is_subscription_sponsored', false)
                    ->where(function ($q): void {
                        $q->whereNull('boost_expires_at')->orWhere('boost_expires_at', '<', now());
                    })
                    ->when($type !== null, fn ($q) => $q->whereHas('ad_type', fn ($sub) => $sub->where('name', 'ilike', "%{$type}%")))
                    ->when($excludeIds !== [], fn ($q) => $q->whereNotIn('id', $excludeIds))
                    ->orderByDesc('created_at')
                    ->limit($perPage * 2)
                    ->get();

                if ($organicBoost->isNotEmpty()) {
                    $candidates = $candidates->concat($organicBoost);
                }
            }

            $distributed = $this->feedRanker->distribute($candidates, $perPage);
            $paginator->setCollection($distributed);
            $this->feedRanker->recordImpressions($distributed);
        }

        // Resolve free-text `$type` to a known AdType id so the count
        // query becomes a single indexed `WHERE type_id = ?` instead of
        // a correlated EXISTS with `ILIKE`. Also caps cache-key
        // fragmentation — bots feeding random `type=` values used to
        // mint a fresh cache entry per unique string; now unknown types
        // bypass the cache and return the overall total.
        $typeId = null;
        if ($type !== null) {
            $typeId = Cache::remember(
                'ads:feed:type_id:'.sha1($type),
                3600,
                fn () => AdType::query()
                    ->where('name', 'ilike', "%{$type}%")
                    ->value('id')
            );
        }

        /** @var int $total */
        $total = Cache::remember(
            'ads:feed:total:'.($typeId ?? 'all'),
            600,
            function () use ($typeId): int {
                $q = Ad::query()->visible()->publiclyListed();
                if ($typeId !== null) {
                    $q->where('type_id', $typeId);
                }

                return $q->count();
            }
        );

        return new AdFeedResult($paginator, $total);
    }
}
