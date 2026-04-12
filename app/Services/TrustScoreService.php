<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\TrustScoreServiceInterface;
use App\Enums\PaymentStatus;
use App\Enums\ReservationStatus;
use App\Enums\TrustScoreTier;
use App\Enums\UserRole;
use App\Models\Review;
use App\Models\TentativeReservation;
use App\Models\TrustScore;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

/**
 * Computes a bidirectional TrustScore (0–100) for users.
 *
 * Tenant scoring (7 signals, 100 pts max):
 *  - Payment reliability   : 10 pts
 *  - Viewing attendance     : 20 pts
 *  - Profile completeness   : 15 pts
 *  - Reviews from landlords : 20 pts
 *  - Account maturity       : 10 pts
 *  - Document uploads       : 15 pts
 *  - Verification status    : 10 pts
 *
 * Landlord scoring (7 signals, 100 pts max):
 *  - Ad quality (KeyScore)  : 15 pts
 *  - Response rate          : 15 pts
 *  - Reviews from tenants   : 25 pts
 *  - Profile completeness   : 10 pts
 *  - Lease completion       : 15 pts
 *  - Account maturity       : 10 pts
 *  - Verification status    : 10 pts
 */
final readonly class TrustScoreService implements TrustScoreServiceInterface
{
    private const int CACHE_TTL = 3600; // 1 hour

    public function __construct(
        private KeyScoreService $keyScoreService,
    ) {}

    /**
     * Compute and persist the trust score for a user.
     *
     * @return array{score: int, tier: TrustScoreTier, breakdown: array<string, mixed>, label: string}
     */
    public function compute(User $user): array
    {
        $roleContext = $this->resolveRoleContext($user);

        $breakdown = $roleContext === 'landlord'
            ? $this->computeLandlordScore($user)
            : $this->computeTenantScore($user);

        $total = min(100, (int) round(array_sum(array_column($breakdown, 'score'))));
        $tier = TrustScoreTier::fromScore($total);

        TrustScore::updateOrCreate(
            ['user_id' => $user->id, 'role_context' => $roleContext],
            [
                'score' => $total,
                'tier' => $tier,
                'components' => $breakdown,
                'computed_at' => now(),
            ],
        );

        $result = [
            'score' => $total,
            'tier' => $tier,
            'breakdown' => $breakdown,
            'label' => $tier->label(),
        ];

        Cache::put($this->cacheKey($user, $roleContext), $result, self::CACHE_TTL);

        return $result;
    }

    /**
     * Get cached score or compute fresh.
     *
     * @return array{score: int, tier: TrustScoreTier, breakdown: array<string, mixed>, label: string}
     */
    public function getOrCompute(User $user): array
    {
        $roleContext = $this->resolveRoleContext($user);

        return Cache::remember(
            $this->cacheKey($user, $roleContext),
            self::CACHE_TTL,
            fn (): array => $this->compute($user),
        );
    }

    /**
     * Invalidate cached score (call after relevant data changes).
     */
    public function invalidate(User $user): void
    {
        $roleContext = $this->resolveRoleContext($user);
        Cache::forget($this->cacheKey($user, $roleContext));
    }

    // ─── Tenant scoring ──────────────────────────────────────────────────

    /** @return array<string, array{score: int, max: int, label: string, value: string, tip: string}> */
    private function computeTenantScore(User $user): array
    {
        return [
            'payment_reliability' => $this->scoreTenantPayments($user),
            'viewing_attendance' => $this->scoreTenantViewings($user),
            'profile_completeness' => $this->scoreTenantProfile($user),
            'reviews' => $this->scoreTenantReviews($user),
            'account_maturity' => $this->scoreAccountMaturity($user),
            'documents' => $this->scoreTenantDocuments($user),
            'verification' => $this->scoreVerification($user),
        ];
    }

    private function scoreTenantPayments(User $user): array
    {
        $payments = $user->payments()->get(['status']);
        $total = $payments->count();

        if ($total === 0) {
            return ['score' => 3, 'max' => 10, 'label' => 'Fiabilité paiements', 'value' => 'Aucun paiement', 'tip' => 'Effectuez un premier paiement pour améliorer votre score.'];
        }

        $successful = $payments->where('status', PaymentStatus::SUCCESS)->count();
        $ratio = $successful / $total;

        $score = match (true) {
            $ratio >= 0.95 => 10,
            $ratio >= 0.80 => 8,
            $ratio >= 0.60 => 5,
            default => 2,
        };

        return ['score' => $score, 'max' => 10, 'label' => 'Fiabilité paiements', 'value' => "{$successful}/{$total} réussis", 'tip' => 'Maintenez un bon historique de paiements.'];
    }

    private function scoreTenantViewings(User $user): array
    {
        $reservations = $user->clientReservations()->get(['status']);
        $total = $reservations->count();

        if ($total === 0) {
            return ['score' => 5, 'max' => 20, 'label' => 'Assiduité visites', 'value' => 'Aucune visite', 'tip' => 'Réservez et honorez des visites pour améliorer votre score.'];
        }

        $confirmed = $reservations->where('status', ReservationStatus::Confirmed)->count();
        $cancelled = $reservations->where('status', ReservationStatus::Cancelled)->count();
        $attendanceRate = $confirmed / $total;
        $cancelRate = $cancelled / $total;

        $score = match (true) {
            $attendanceRate >= 0.90 && $cancelRate < 0.05 => 20,
            $attendanceRate >= 0.75 => 16,
            $attendanceRate >= 0.50 => 10,
            $attendanceRate >= 0.30 => 6,
            default => 2,
        };

        return ['score' => $score, 'max' => 20, 'label' => 'Assiduité visites', 'value' => "{$confirmed}/{$total} honorées", 'tip' => 'Honorez vos rendez-vous de visite pour un meilleur score.'];
    }

    private function scoreTenantProfile(User $user): array
    {
        $checks = [
            'avatar' => filled($user->avatar),
            'phone' => filled($user->phone_number),
            'bio' => mb_strlen((string) ($user->bio ?? '')) >= 20,
            'city' => filled($user->city_id),
            'name' => filled($user->firstname) && filled($user->lastname),
        ];

        $completed = count(array_filter($checks));
        $score = (int) round(($completed / count($checks)) * 15);

        return ['score' => $score, 'max' => 15, 'label' => 'Profil complet', 'value' => "{$completed}/".count($checks).' éléments', 'tip' => 'Complétez votre profil (photo, bio, téléphone, ville).'];
    }

    private function scoreTenantReviews(User $user): array
    {
        $reviews = $user->reviews()->get(['rating']);
        $count = $reviews->count();

        if ($count === 0) {
            return ['score' => 0, 'max' => 20, 'label' => 'Avis reçus', 'value' => 'Aucun avis', 'tip' => 'Les avis positifs de bailleurs améliorent fortement votre score.'];
        }

        $avg = $reviews->avg('rating');

        $score = match (true) {
            $avg >= 4.5 && $count >= 3 => 20,
            $avg >= 4.0 && $count >= 2 => 16,
            $avg >= 3.5 => 12,
            $avg >= 3.0 => 8,
            default => 4,
        };

        return ['score' => $score, 'max' => 20, 'label' => 'Avis reçus', 'value' => number_format((float) $avg, 1)."/5 ({$count} avis)", 'tip' => 'Recueillez des avis positifs après vos locations.'];
    }

    private function scoreAccountMaturity(User $user): array
    {
        $daysSinceCreation = (int) $user->created_at->diffInDays(now());
        $interactionCount = $user->adInteractions()->count();

        $ageScore = match (true) {
            $daysSinceCreation >= 365 => 5,
            $daysSinceCreation >= 180 => 4,
            $daysSinceCreation >= 90 => 3,
            $daysSinceCreation >= 30 => 2,
            default => 1,
        };

        $activityScore = match (true) {
            $interactionCount >= 50 => 5,
            $interactionCount >= 20 => 4,
            $interactionCount >= 10 => 3,
            $interactionCount >= 3 => 2,
            default => 1,
        };

        $score = $ageScore + $activityScore;

        return ['score' => $score, 'max' => 10, 'label' => 'Ancienneté', 'value' => "{$daysSinceCreation} jours, {$interactionCount} interactions", 'tip' => 'Utilisez régulièrement la plateforme pour montrer votre engagement.'];
    }

    private function scoreTenantDocuments(User $user): array
    {
        $documents = $user->documents()->get(['id']);
        $count = $documents->count();

        $score = match (true) {
            $count >= 3 => 15,
            $count >= 2 => 12,
            $count >= 1 => 8,
            default => 0,
        };

        return ['score' => $score, 'max' => 15, 'label' => 'Documents', 'value' => "{$count} document(s) fourni(s)", 'tip' => "Ajoutez votre pièce d'identité et justificatifs pour gagner en confiance."];
    }

    private function scoreVerification(User $user): array
    {
        $checks = [
            'email' => $user->email_verified_at !== null,
            'phone' => $user->phone_number !== null,
        ];

        $score = 0;
        if ($checks['email']) {
            $score += 5;
        }
        if ($checks['phone']) {
            // Phone number provided — partial credit (no phone verification column yet)
            $score += 3;
        }

        $verified = array_filter($checks);

        return ['score' => $score, 'max' => 10, 'label' => 'Vérification', 'value' => count($verified).' vérification(s)', 'tip' => 'Vérifiez votre email et votre téléphone.'];
    }

    // ─── Landlord scoring ─────────────────────────────────────────────────

    /** @return array<string, array{score: int, max: int, label: string, value: string, tip: string}> */
    private function computeLandlordScore(User $user): array
    {
        return [
            'ad_quality' => $this->scoreLandlordAdQuality($user),
            'response_rate' => $this->scoreLandlordResponseRate($user),
            'reviews' => $this->scoreLandlordReviews($user),
            'profile_completeness' => $this->scoreLandlordProfile($user),
            'lease_completion' => $this->scoreLandlordLeases($user),
            'account_maturity' => $this->scoreAccountMaturity($user),
            'verification' => $this->scoreLandlordVerification($user),
        ];
    }

    private function scoreLandlordAdQuality(User $user): array
    {
        $ads = $user->ads()->where('status', 'available')->get();

        if ($ads->isEmpty()) {
            return ['score' => 0, 'max' => 15, 'label' => 'Qualité annonces', 'value' => 'Aucune annonce', 'tip' => 'Publiez des annonces de qualité avec photos et descriptions détaillées.'];
        }

        $totalKeyScore = 0;
        foreach ($ads as $ad) {
            $keyScore = $this->keyScoreService->compute($ad);
            $totalKeyScore += $keyScore['score'];
        }

        $avgKeyScore = $totalKeyScore / $ads->count();

        $score = match (true) {
            $avgKeyScore >= 80 => 15,
            $avgKeyScore >= 65 => 12,
            $avgKeyScore >= 50 => 9,
            $avgKeyScore >= 35 => 6,
            default => 3,
        };

        return ['score' => $score, 'max' => 15, 'label' => 'Qualité annonces', 'value' => (int) round($avgKeyScore).'/100 KeyScore moyen', 'tip' => 'Améliorez vos annonces (photos, description, équipements).'];
    }

    private function scoreLandlordResponseRate(User $user): array
    {
        $adIds = $user->ads()->pluck('id');

        if ($adIds->isEmpty()) {
            return ['score' => 5, 'max' => 15, 'label' => 'Réactivité', 'value' => 'Aucune demande', 'tip' => 'Répondez rapidement aux demandes de visite.'];
        }

        $reservations = TentativeReservation::whereIn('ad_id', $adIds)->get(['status']);
        $total = $reservations->count();

        if ($total === 0) {
            return ['score' => 5, 'max' => 15, 'label' => 'Réactivité', 'value' => 'Aucune demande', 'tip' => 'Répondez rapidement aux demandes de visite.'];
        }

        $confirmed = $reservations->where('status', ReservationStatus::Confirmed)->count();
        $rate = $confirmed / $total;

        $score = match (true) {
            $rate >= 0.85 => 15,
            $rate >= 0.65 => 12,
            $rate >= 0.45 => 8,
            $rate >= 0.25 => 5,
            default => 2,
        };

        return ['score' => $score, 'max' => 15, 'label' => 'Réactivité', 'value' => round($rate * 100).'% de confirmations', 'tip' => 'Confirmez les demandes de visite rapidement.'];
    }

    private function scoreLandlordReviews(User $user): array
    {
        $adIds = $user->ads()->pluck('id');
        $reviews = Review::whereIn('ad_id', $adIds)->get(['rating']);
        $count = $reviews->count();

        if ($count === 0) {
            return ['score' => 0, 'max' => 25, 'label' => 'Avis locataires', 'value' => 'Aucun avis', 'tip' => 'Encouragez vos locataires à laisser un avis.'];
        }

        $avg = $reviews->avg('rating');

        $score = match (true) {
            $avg >= 4.5 && $count >= 5 => 25,
            $avg >= 4.0 && $count >= 3 => 20,
            $avg >= 3.5 && $count >= 2 => 15,
            $avg >= 3.0 => 10,
            default => 5,
        };

        return ['score' => $score, 'max' => 25, 'label' => 'Avis locataires', 'value' => number_format((float) $avg, 1)."/5 ({$count} avis)", 'tip' => 'Les bons avis de locataires renforcent votre crédibilité.'];
    }

    private function scoreLandlordProfile(User $user): array
    {
        $checks = [
            'avatar' => filled($user->avatar),
            'phone' => filled($user->phone_number),
            'bio' => mb_strlen((string) ($user->bio ?? '')) >= 20,
            'agency' => filled($user->agency_id),
            'name' => filled($user->firstname) && filled($user->lastname),
        ];

        $completed = count(array_filter($checks));
        $score = (int) round(($completed / count($checks)) * 10);

        return ['score' => $score, 'max' => 10, 'label' => 'Profil complet', 'value' => "{$completed}/".count($checks).' éléments', 'tip' => 'Complétez votre profil pour inspirer confiance.'];
    }

    private function scoreLandlordLeases(User $user): array
    {
        $leaseCount = $user->leaseContracts()->count();

        $score = match (true) {
            $leaseCount >= 10 => 15,
            $leaseCount >= 5 => 12,
            $leaseCount >= 3 => 9,
            $leaseCount >= 1 => 5,
            default => 0,
        };

        return ['score' => $score, 'max' => 15, 'label' => 'Baux signés', 'value' => "{$leaseCount} bail/baux", 'tip' => 'Chaque bail complété améliore votre réputation.'];
    }

    private function scoreLandlordVerification(User $user): array
    {
        $score = 0;

        if ($user->email_verified_at !== null) {
            $score += 3;
        }
        if ($user->phone_number !== null) {
            $score += 2;
        }
        if ($user->agency_id !== null) {
            $score += 3;
        }
        if ($user->is_verified ?? false) {
            $score += 2;
        }

        return ['score' => min(10, $score), 'max' => 10, 'label' => 'Vérification', 'value' => "{$score}/10 vérifications", 'tip' => 'Vérifiez votre identité et rattachez-vous à une agence.'];
    }

    // ─── Helpers ──────────────────────────────────────────────────────────

    private function resolveRoleContext(User $user): string
    {
        return $user->role === UserRole::AGENT ? 'landlord' : 'tenant';
    }

    private function cacheKey(User $user, string $roleContext): string
    {
        return "trust_score:{$user->id}:{$roleContext}";
    }
}
