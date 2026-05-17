<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Models\AdInteraction;
use App\Models\LeaseContract;
use App\Models\SiteVisit;
use App\Models\TentativeReservation;
use App\Models\UnlockedAd;
use App\Models\User;
use App\Support\MetricsPeriod;
use Illuminate\Support\Facades\Cache;

/**
 * Builds the multi-step conversion funnel from site visitor through lease signing.
 */
final class ConversionFunnelService
{
    private const int CACHE_TTL = 300;

    /**
     * @return array{steps: array<int, array{label: string, count: int, rate: float, drop_off: float}>}
     */
    public function get(string $period = '30d'): array
    {
        $since = MetricsPeriod::toDate($period);

        return Cache::remember("admin_funnel_{$period}", self::CACHE_TTL, function () use ($since) {
            $visitors = SiteVisit::where('visited_at', '>=', $since)->distinct('session_id')->count('session_id');
            $inscriptions = User::where('created_at', '>=', $since)->count();
            $searches = AdInteraction::where('type', AdInteraction::TYPE_SEARCH)->where('created_at', '>=', $since)->distinct('user_id')->count('user_id');
            $unlocks = UnlockedAd::where('unlocked_at', '>=', $since)->distinct('user_id')->count('user_id');
            $visits = TentativeReservation::where('created_at', '>=', $since)->distinct('client_id')->count('client_id');
            $locations = LeaseContract::where('created_at', '>=', $since)->count();

            $steps = [
                ['label' => '1. Visiteurs du site', 'count' => $visitors],
                ['label' => '2. Création de compte', 'count' => $inscriptions],
                ['label' => '3. Recherche de logement', 'count' => $searches],
                ['label' => '4. Déblocage d\'un contact', 'count' => $unlocks],
                ['label' => '5. Demande de visite', 'count' => $visits],
                ['label' => '6. Signature de bail', 'count' => $locations],
            ];

            $result = [];
            foreach ($steps as $i => $step) {
                $prevCount = $i > 0 ? $steps[$i - 1]['count'] : $step['count'];
                $rate = $prevCount > 0 ? round(($step['count'] / $prevCount) * 100, 1) : 0;
                $dropOff = $i > 0 ? round(100 - $rate, 1) : 0;

                $result[] = [
                    'label' => $step['label'],
                    'count' => $step['count'],
                    'rate' => $i === 0 ? 100.0 : $rate,
                    'drop_off' => $dropOff,
                ];
            }

            return ['steps' => $result];
        });
    }
}
