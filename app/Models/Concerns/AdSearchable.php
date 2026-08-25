<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Enums\PropertyAttribute;
use Illuminate\Database\Eloquent\Builder;

/**
 * Scout search-document construction for an ad: the searchable payload,
 * bulk-indexing eager loads, furnished detection and the Meilisearch
 * relevance score. The thin Scout hooks (toSearchableArray /
 * makeAllSearchableUsing / shouldBeSearchable) stay on the model and
 * delegate here to avoid colliding with the Searchable trait defaults.
 */
trait AdSearchable
{
    /**
     * Build the Meilisearch document for this ad.
     */
    public function buildSearchableArray(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'adresse' => $this->adresse,
            'price' => (float) $this->price,
            'surface_area' => (float) $this->surface_area,
            'bedrooms' => (int) $this->bedrooms,
            'bathrooms' => (int) $this->bathrooms,
            'has_parking' => (bool) $this->has_parking,
            'has_3d_tour' => (bool) $this->has_3d_tour,
            'is_verified' => (bool) $this->is_verified,
            'is_furnished' => $this->isFurnishedForSearch(),
            'status' => $this->status,
            'is_visible' => (bool) $this->is_visible,

            // Relations — vérifier qu'elles existent
            'city' => $this->quarter?->city?->name,
            'city_id' => $this->quarter?->city_id,
            'country' => $this->quarter?->city?->country,
            'quarter' => $this->quarter?->name,
            'type' => $this->ad_type?->name,
            'type_id' => $this->type_id,
            'quarter_id' => $this->quarter_id,
            'transaction_type' => $this->transaction_type?->value,
            'price_period' => $this->price_period,

            // Pour la recherche géographique (optionnel)
            '_geo' => $this->location ? [
                'lat' => $this->location->getY(),
                'lng' => $this->location->getX(),
            ] : null,

            'created_at' => $this->created_at?->timestamp,

            // Rating & popularity — use eager-loaded aggregates (see eagerLoadForSearch).
            // Fallback to 0 if the withAvg/withCount was not applied (e.g. single-model scout index).
            'reviews_avg_rating' => (float) ($this->reviews_avg_rating ?? 0),
            'views_count' => (int) ($this->views_count ?? 0),
            'unlocked_count' => (int) ($this->unlocked_count ?? 0),
            'contact_count' => (int) ($this->contact_count ?? 0),
            'relevance_score' => $this->computeRelevanceScore(),

            // Boost
            'is_boosted' => (bool) $this->is_boosted,
            'boost_score' => (int) $this->boost_score,
            'boost_expires_at' => $this->boost_expires_at?->timestamp,

            // Amenity slugs for filter-by-attribute support
            'attributes' => $this->getAttribute('attributes') ?? [],
        ];
    }

    /**
     * True when the listing is furnished (explicit attribute and/or type name typical of meublé listings).
     */
    public function isFurnishedForSearch(): bool
    {
        $attrs = $this->getAttribute('attributes');
        if (is_array($attrs) && in_array(PropertyAttribute::Furnished->value, $attrs, true)) {
            return true;
        }

        $typeName = $this->ad_type !== null ? $this->ad_type->name : '';

        return (bool) preg_match('/meubl/i', (string) $typeName);
    }

    /**
     * Relevance score for Meilisearch custom ranking (0–100).
     *
     * Formula:
     *   CTR      = unlocked_count / max(views_count, 1)       → weight 40
     *   Rating   = reviews_avg_rating / 5                     → weight 30
     *   Boost    = ×1.5 multiplier if currently boosted       → weight 30
     *   Behavior = (contact_count / max(views_count, 1)) × 10 → bonus up to 10
     *
     * Scores are clamped to [0, 100].
     */
    public function computeRelevanceScore(): int
    {
        $views = max((int) ($this->views_count ?? 0), 1);
        $unlocks = (int) ($this->unlocked_count ?? 0);
        $rating = (float) ($this->reviews_avg_rating ?? 0);
        $contacts = (int) ($this->contact_count ?? 0);

        $ctr = min($unlocks / $views, 1.0);
        $ratingN = min($rating / 5.0, 1.0);
        $behavior = min($contacts / $views, 1.0);

        $base = $ctr * 40 + $ratingN * 30 + $behavior * 10;

        if ($this->isBoosted()) {
            $base *= 1.5;
        }

        return (int) min(round($base), 100);
    }

    /**
     * Eager-load everything buildSearchableArray() needs so bulk indexing
     * (Ad::all()->searchable()) does not fire an extra query per model.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    protected function eagerLoadForSearch(Builder $query): Builder
    {
        // PERF-W24: eager-load everything toSearchableArray() needs so bulk indexing
        // (Ad::all()->searchable()) does not fire an extra query per model.
        return $query
            ->with(['quarter.city', 'ad_type'])
            ->withAvg('reviews', 'rating')
            ->withCount([
                'interactions as views_count' => fn (Builder $q) => $q->where('type', 'view'),
                'interactions as unlocked_count' => fn (Builder $q) => $q->where('type', 'unlock'),
                'interactions as contact_count' => fn (Builder $q) => $q->whereIn('type', ['contact', 'whatsapp']),
            ]);
    }
}
