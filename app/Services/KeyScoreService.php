<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Ad;
use Illuminate\Support\Facades\Cache;

/**
 * Calculates a KeyScore (0–100) for an Ad.
 *
 * Scoring breakdown:
 *  - Photos quality     : 20 pts  (≥5 photos = full)
 *  - Description        : 15 pts  (≥200 chars = full)
 *  - Price vs market    : 20 pts  (below median = full, above = partial)
 *  - Location pinned    : 10 pts  (GPS coordinates set)
 *  - Attributes         : 15 pts  (≥5 attributes = full)
 *  - Responsiveness     : 10 pts  (based on view/interaction ratio)
 *  - Freshness          : 10 pts  (published < 30 days ago = full)
 */
final class KeyScoreService
{
    public function compute(Ad $ad): array
    {
        $breakdown = [
            'photos' => $this->scorePhotos($ad),
            'description' => $this->scoreDescription($ad),
            'price' => $this->scorePrice($ad),
            'location' => $this->scoreLocation($ad),
            'attributes' => $this->scoreAttributes($ad),
            'responsiveness' => $this->scoreResponsiveness($ad),
            'freshness' => $this->scoreFreshness($ad),
        ];

        $total = array_sum(array_column($breakdown, 'score'));

        return [
            'score' => min(100, (int) round($total)),
            'breakdown' => $breakdown,
            'label' => $this->getLabel($total),
        ];
    }

    private function scorePhotos(Ad $ad): array
    {
        $count = $ad->getMedia('images')->count();
        $score = match (true) {
            $count >= 8 => 20,
            $count >= 5 => 15,
            $count >= 3 => 10,
            $count >= 1 => 5,
            default => 0,
        };

        return ['score' => $score, 'max' => 20, 'label' => 'Photos', 'value' => $count.' photo'.($count > 1 ? 's' : '')];
    }

    private function scoreDescription(Ad $ad): array
    {
        $len = mb_strlen($ad->description ?? '');
        $score = match (true) {
            $len >= 300 => 15,
            $len >= 150 => 10,
            $len >= 50 => 5,
            default => 0,
        };

        return ['score' => $score, 'max' => 15, 'label' => 'Description', 'value' => $len.' caractère'.($len > 1 ? 's' : '')];
    }

    private function scorePrice(Ad $ad): array
    {
        if (!$ad->price || !$ad->quarter_id) {
            return ['score' => 10, 'max' => 20, 'label' => 'Prix', 'value' => 'Non évalué'];
        }

        $cacheKey = 'median_price_'.$ad->quarter_id.'_'.($ad->type_id ?? 'all');
        $median = Cache::remember($cacheKey, 3600, fn (): ?float => Ad::query()
            ->where('quarter_id', $ad->quarter_id)
            ->when($ad->type_id, fn ($q) => $q->where('type_id', $ad->type_id))
            ->whereNotNull('price')
            ->where('price', '>', 0)
            ->selectRaw('PERCENTILE_CONT(0.5) WITHIN GROUP (ORDER BY price) as median')
            ->value('median'));

        if (!$median) {
            return ['score' => 10, 'max' => 20, 'label' => 'Prix', 'value' => 'Non évalué'];
        }

        $ratio = $ad->price / $median;
        $score = match (true) {
            $ratio <= 0.85 => 20,
            $ratio <= 1.0 => 16,
            $ratio <= 1.15 => 12,
            $ratio <= 1.30 => 8,
            default => 4,
        };

        $diff = round(($ratio - 1) * 100);
        $label = $diff > 0 ? "+{$diff}% vs marché" : "{$diff}% vs marché";

        return ['score' => $score, 'max' => 20, 'label' => 'Prix', 'value' => $label];
    }

    private function scoreLocation(Ad $ad): array
    {
        $hasLocation = $ad->location !== null;
        $score = $hasLocation ? 10 : 0;

        return ['score' => $score, 'max' => 10, 'label' => 'Localisation', 'value' => $hasLocation ? 'GPS défini' : 'Non géolocalisé'];
    }

    private function scoreAttributes(Ad $ad): array
    {
        $count = count($ad->attributes ?? []);
        $score = match (true) {
            $count >= 6 => 15,
            $count >= 4 => 12,
            $count >= 2 => 8,
            $count >= 1 => 4,
            default => 0,
        };

        return ['score' => $score, 'max' => 15, 'label' => 'Équipements', 'value' => $count.' équipement'.($count > 1 ? 's' : '')];
    }

    private function scoreResponsiveness(Ad $ad): array
    {
        $views = $ad->views_count ?? 0;
        $interactions = $ad->interactions()->count();
        $ratio = $views > 0 ? $interactions / $views : 0;

        $score = match (true) {
            $ratio >= 0.1 => 10,
            $ratio >= 0.05 => 7,
            $ratio >= 0.02 => 4,
            default => 2,
        };

        return ['score' => $score, 'max' => 10, 'label' => 'Popularité', 'value' => $views.' vue'.($views > 1 ? 's' : '')];
    }

    private function scoreFreshness(Ad $ad): array
    {
        $days = (int) round($ad->created_at->diffInHours(now()) / 24);
        $score = match (true) {
            $days <= 7 => 10,
            $days <= 30 => 7,
            $days <= 90 => 4,
            default => 1,
        };

        return ['score' => $score, 'max' => 10, 'label' => 'Fraîcheur', 'value' => $days.' jour'.($days > 1 ? 's' : '')];
    }

    private function getLabel(float $score): string
    {
        return match (true) {
            $score >= 85 => 'Excellent',
            $score >= 70 => 'Très bon',
            $score >= 55 => 'Bon',
            $score >= 40 => 'Moyen',
            default => 'À améliorer',
        };
    }
}
