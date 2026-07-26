<?php

declare(strict_types=1);

namespace App\Services\User;

use App\Enums\UserRole;
use App\Http\Resources\AdResource;
use App\Models\Ad;
use App\Models\AdInteraction;
use App\Models\Agency;
use App\Models\UnlockedAd;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Handles public profile assembly, response-time computation,
 * trust-score resolution, and unlocked-ads retrieval.
 *
 * Extracted from UserController to respect SRP — the controller
 * remains a thin HTTP adapter.
 */
final readonly class UserProfileService
{
    /**
     * Build the full public-profile payload for a landlord / agent.
     *
     * @return array{data: array<string, mixed>, ads: mixed, meta: array<string, int>}
     */
    public function buildPublicProfile(User $user, int $perPage = 12): array
    {
        $user->load(['city', 'agency']);

        $avatarUrl = $this->resolveAvatarUrl($user);

        $ads = Ad::where('user_id', $user->id)
            ->where('status', 'available')
            ->with([
                'user:id,username,firstname,lastname,phone_number,phone_is_whatsapp,email,avatar' => ['agency:id,name,slug,logo'],
                'agency:id,name,slug,logo',
                'quarter:id,name,city_id' => ['city:id,name'],
                'ad_type:id,name,desc',
                'media',
            ])
            ->withAvg('reviews', 'rating')
            ->withCount('reviews')
            ->latest()
            ->paginate($perPage);

        $reviewStats = DB::table('reviews')
            ->join('ad', 'reviews.ad_id', '=', 'ad.id')
            ->where('ad.user_id', $user->id)
            ->whereNull('reviews.deleted_at')
            ->selectRaw('COUNT(*) as total_reviews, ROUND(AVG(reviews.rating), 2) as avg_rating')
            ->first();

        $totalAds = Ad::where('user_id', $user->id)
            ->where('status', 'available')
            ->count();

        $recentReviews = $this->recentReviews($user->id);
        $responseTimeLabel = $this->computeResponseTimeLabel($user->id);

        return [
            'data' => [
                'id' => $user->id,
                'username' => $user->username,
                'firstname' => $user->firstname,
                'lastname' => $user->lastname,
                'display_name' => $user->fullname,
                'bio' => $user->bio,
                'avatar' => $avatarUrl,
                'type' => $user->type?->value,
                'city_name' => $user->city?->name,
                'is_verified' => (bool) $user->email_verified_at,
                'agency' => $user->agency instanceof Agency ? [
                    'id' => $user->agency->id,
                    'name' => $user->agency->name,
                    'slug' => $user->agency->slug,
                    'logo' => $user->agency->logo,
                ] : null,
                'member_since' => $user->created_at,
                'total_active_ads' => $totalAds,
                'review_stats' => [
                    'avg_rating' => (float) ($reviewStats->avg_rating ?? 0),
                    'total_reviews' => (int) ($reviewStats->total_reviews ?? 0),
                ],
                'response_time_label' => $responseTimeLabel,
                'recent_reviews' => $recentReviews,
                'trust_score' => $this->getTrustScoreData($user),
            ],
            'ads' => AdResource::collection($ads->items()),
            'meta' => [
                'total' => $ads->total(),
                'current_page' => $ads->currentPage(),
                'last_page' => $ads->lastPage(),
                'per_page' => $ads->perPage(),
            ],
        ];
    }

    /**
     * Resolve the user's avatar URL (Spatie Media → avatar column → null).
     */
    public function resolveAvatarUrl(User $user): ?string
    {
        return $user->resolveChatAvatarUrl();
    }

    /**
     * Compute a human-readable response time label from recent viewing reservations.
     */
    public function computeResponseTimeLabel(string $userId): ?string
    {
        try {
            $adIds = Ad::where('user_id', $userId)->pluck('id');
            if ($adIds->isEmpty()) {
                return null;
            }

            $avgHours = DB::transaction(fn () => DB::table('viewing_reservations as vr')
                ->whereIn('vr.ad_id', $adIds)
                ->whereIn('vr.status', ['confirmed', 'declined'])
                ->whereNotNull('vr.responded_at')
                ->whereRaw("vr.responded_at > NOW() - INTERVAL '60 days'")
                ->selectRaw('AVG(EXTRACT(EPOCH FROM (vr.responded_at - vr.created_at)) / 3600) as avg_hours')
                ->value('avg_hours'));

            if ($avgHours === null) {
                return null;
            }

            $h = (float) $avgHours;
            if ($h < 1) {
                return "Répond en moins d'1h";
            }
            if ($h < 2) {
                return 'Répond en moins de 2h';
            }
            if ($h < 6) {
                return 'Répond en moins de 6h';
            }
            if ($h < 24) {
                return 'Répond en moins de 24h';
            }

            return 'Répond sous quelques jours';
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Return trust-score summary for a user (null if no consent or no score).
     *
     * @return array{score: int, tier: string, tier_label: string, tier_color: string}|null
     */
    public function getTrustScoreData(User $user): ?array
    {
        if (!$user->trust_score_consent) {
            return null;
        }

        $roleContext = $user->role === UserRole::AGENT ? 'landlord' : 'tenant';
        $trustScore = $user->trustScores()->where('role_context', $roleContext)->first();

        if (!$trustScore) {
            return null;
        }

        return [
            'score' => $trustScore->score,
            'tier' => $trustScore->tier->value,
            'tier_label' => $trustScore->tier->label(),
            'tier_color' => $trustScore->tier->hexColor(),
        ];
    }

    /**
     * Return the ads unlocked by the given user.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, Ad>
     */
    public function unlockedAds(string $userId): \Illuminate\Database\Eloquent\Collection
    {
        $adIds = UnlockedAd::where('user_id', $userId)->pluck('ad_id');

        return Ad::with([
            'quarter:id,name,city_id' => ['city:id,name'],
            'ad_type:id,name,desc',
            'media',
            'user:id,username,firstname,lastname,phone_number,phone_is_whatsapp,email,avatar' => ['agency:id,name,slug,logo'],
            'agency:id,name,slug,logo',
        ])
            ->withAvg('reviews', 'rating')
            ->withCount([
                'reviews',
                'interactions as views_count' => fn ($q) => $q->where('type', AdInteraction::TYPE_VIEW),
            ])
            ->whereIn('id', $adIds)
            ->latest()
            ->get();
    }

    /**
     * Recent reviews with reviewer info (last 5 with a comment).
     */
    private function recentReviews(string $userId): Collection
    {
        return DB::table('reviews')
            ->join('ad', 'reviews.ad_id', '=', 'ad.id')
            ->join('users as reviewer', 'reviews.user_id', '=', 'reviewer.id')
            ->where('ad.user_id', $userId)
            ->whereNull('reviews.deleted_at')
            ->whereNotNull('reviews.comment')
            ->where('reviews.comment', '!=', '')
            ->select(
                'reviews.id',
                'reviews.rating',
                'reviews.comment',
                'reviews.created_at',
                'reviewer.firstname as reviewer_firstname',
                'reviewer.lastname as reviewer_lastname',
                'ad.title as ad_title',
            )
            ->orderByDesc('reviews.created_at')
            ->limit(5)
            ->get()
            ->map(fn ($r) => [
                'id' => $r->id,
                'rating' => (int) $r->rating,
                'comment' => $r->comment,
                'created_at' => $r->created_at,
                'reviewer_name' => trim($r->reviewer_firstname.' '.$r->reviewer_lastname),
                'ad_title' => $r->ad_title,
            ]);
    }
}
