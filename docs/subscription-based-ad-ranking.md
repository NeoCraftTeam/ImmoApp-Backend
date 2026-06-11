# Subscription-Based Ad Ranking System (Facebook Ads Style)

## Executive Summary

When an owner/agency subscribes to a KeyHome plan, **ALL their ads are automatically sponsored** for the duration of the subscription. This document outlines the ranking algorithm that prioritizes sponsored ads in the feed, similar to Facebook's ad boost system.

## Current System

### Existing Boost Infrastructure
- ✅ `ad_boosts` table for manual boosts
- ✅ `boost_score` column on `ad` table
- ✅ `is_boosted`, `boost_expires_at`, `boosted_at` fields
- ✅ `boost()` and `unboost()` methods on Ad model
- ✅ Meilisearch integration with `boost_score:desc` ranking
- ✅ `computeRelevanceScore()` algorithm (0-100 scale)

### Current Ranking Formula
```
Base Score = (CTR × 40) + (Rating × 30) + (Behavior × 10)
If boosted: Score × 1.5
Clamped to [0, 100]
```

## New Subscription-Based Ranking System

### Concept: Automatic Sponsorship

When an agency subscribes:
1. **All existing ads** → automatically sponsored
2. **New ads created** → automatically sponsored
3. **Subscription expires** → all ads lose sponsorship
4. **Manual boosts** → stack on top of subscription boost

### Ranking Tiers

#### Tier 1: Premium Sponsored (Subscription + Manual Boost)
- Agency has active subscription **AND** ad has manual boost
- **Score multiplier**: 2.5×
- **Visual badge**: "⭐ Premium Sponsored"
- **Feed position**: Top of results

#### Tier 2: Subscription Sponsored
- Agency has active subscription
- **Score multiplier**: 1.8×
- **Visual badge**: "✨ Sponsored"
- **Feed position**: Above organic ads

#### Tier 3: Manual Boost Only
- No subscription, but manual boost purchased
- **Score multiplier**: 1.5× (current)
- **Visual badge**: "🚀 Boosted"
- **Feed position**: Below subscription sponsored

#### Tier 4: Organic
- No subscription, no manual boost
- **Score multiplier**: 1.0×
- **Visual badge**: None
- **Feed position**: Standard ranking

### Feed Distribution Strategy (Facebook-Style)

#### Mixed Feed Algorithm

For every 10 ads shown in the feed:
- **Positions 1-3**: Premium Sponsored (Tier 1)
- **Position 4**: Subscription Sponsored (Tier 2)
- **Position 5**: Organic (Tier 4) — quality boost
- **Positions 6-7**: Subscription Sponsored (Tier 2)
- **Position 8**: Manual Boost (Tier 3)
- **Positions 9-10**: Organic (Tier 4)

**Result**: 6/10 ads are subscription-sponsored, maintaining user experience while maximizing subscription value.

#### Rotation Strategy

**Problem**: Same sponsored ads always at top → user fatigue
**Solution**: Rotate sponsored ads based on:
1. **Time decay**: Ads shown recently get lower priority
2. **User engagement**: CTR, unlocks, contacts boost future ranking
3. **Ad freshness**: Newly created ads get temporary boost
4. **Geographic relevance**: Closer = higher priority

### Implementation

#### 1. Database Schema Changes

**Option A: Auto-Boost via Subscription (Recommended)**
```sql
-- No schema changes needed
-- Use existing boost_score + is_boosted fields
-- Auto-boost all ads when subscription is active
```

**Option B: Track Subscription Tier (More Granular)**
```sql
ALTER TABLE ad ADD COLUMN subscription_sponsored BOOLEAN DEFAULT FALSE;
ALTER TABLE ad ADD COLUMN subscription_tier VARCHAR(20) NULL;
ALTER TABLE ad ADD COLUMN last_shown_at TIMESTAMP NULL;
ALTER TABLE ad ADD COLUMN impression_count INT DEFAULT 0;

-- Index for feed ranking
CREATE INDEX idx_ad_feed_ranking ON ad (
    subscription_sponsored DESC,
    boost_score DESC,
    created_at DESC
) WHERE status = 'available' AND is_visible = TRUE;
```

#### 2. Ad Model Methods

```php
// app/Models/Ad.php

/**
 * Check if ad is subscription-sponsored (owner/agency has active subscription)
 */
public function isSubscriptionSponsored(): bool
{
    // If ad belongs to agency, check agency subscription
    if ($this->agency_id) {
        return $this->agency
            ->subscriptions()
            ->where('status', SubscriptionStatus::ACTIVE)
            ->where('ends_at', '>', now())
            ->exists();
    }

    // If ad belongs to individual owner (pro plan)
    if ($this->user && $this->user->user_type === UserType::OWNER) {
        return $this->user
            ->subscriptions()
            ->where('status', SubscriptionStatus::ACTIVE)
            ->where('ends_at', '>', now())
            ->exists();
    }

    return false;
}

/**
 * Get sponsorship tier for ranking
 */
public function getSponsorshipTier(): string
{
    $isSubscriptionSponsored = $this->isSubscriptionSponsored();
    $isManualBoosted = $this->isBoosted(); // existing method

    if ($isSubscriptionSponsored && $isManualBoosted) {
        return 'premium_sponsored'; // Tier 1
    }

    if ($isSubscriptionSponsored) {
        return 'subscription_sponsored'; // Tier 2
    }

    if ($isManualBoosted) {
        return 'manual_boost'; // Tier 3
    }

    return 'organic'; // Tier 4
}

/**
 * Compute ranking score with subscription boost
 */
public function computeRankingScore(): int
{
    $views = max((int) ($this->views_count ?? 0), 1);
    $unlocks = (int) ($this->unlocked_count ?? 0);
    $rating = (float) ($this->reviews_avg_rating ?? 0);
    $contacts = (int) ($this->contact_count ?? 0);

    $ctr = min($unlocks / $views, 1.0);
    $ratingN = min($rating / 5.0, 1.0);
    $behavior = min($contacts / $views, 1.0);

    $base = $ctr * 40 + $ratingN * 30 + $behavior * 10;

    // Apply tier multiplier
    $multiplier = match ($this->getSponsorshipTier()) {
        'premium_sponsored' => 2.5,
        'subscription_sponsored' => 1.8,
        'manual_boost' => 1.5,
        'organic' => 1.0,
    };

    // Time decay: reduce score for recently shown ads
    if ($this->last_shown_at) {
        $minutesSinceShown = now()->diffInMinutes($this->last_shown_at);
        if ($minutesSinceShown < 60) {
            $decayFactor = 1.0 - (0.3 * (1 - $minutesSinceShown / 60));
            $multiplier *= $decayFactor;
        }
    }

    $score = $base * $multiplier;

    return (int) min(round($score), 250); // Increased ceiling for sponsored ads
}

/**
 * Record ad impression (for rotation strategy)
 */
public function recordImpression(): void
{
    $this->increment('impression_count');
    $this->update(['last_shown_at' => now()]);
}

/**
 * Scope: Order by subscription sponsorship tier
 */
#[Scope]
protected function orderBySponsorship($query)
{
    return $query
        ->selectRaw('*, (
            CASE
                WHEN is_subscription_sponsored = TRUE AND is_boosted = TRUE THEN 4
                WHEN is_subscription_sponsored = TRUE THEN 3
                WHEN is_boosted = TRUE THEN 2
                ELSE 1
            END
        ) as sponsorship_tier')
        ->orderByDesc('sponsorship_tier')
        ->orderByDesc('boost_score')
        ->orderByDesc('created_at');
}
```

#### 3. Subscription Observer (Auto-Boost)

```php
// app/Observers/SubscriptionObserver.php

namespace App\Observers;

use App\Models\Subscription;
use App\Models\Ad;
use App\Enums\SubscriptionStatus;

class SubscriptionObserver
{
    /**
     * When subscription becomes active, auto-boost all agency ads
     */
    public function updated(Subscription $subscription): void
    {
        if ($subscription->wasChanged('status')) {
            if ($subscription->status === SubscriptionStatus::ACTIVE) {
                $this->boostAllAgencyAds($subscription);
            } elseif ($subscription->status === SubscriptionStatus::EXPIRED) {
                $this->unboostAllAgencyAds($subscription);
            }
        }
    }

    /**
     * When subscription is created and active
     */
    public function created(Subscription $subscription): void
    {
        if ($subscription->status === SubscriptionStatus::ACTIVE) {
            $this->boostAllAgencyAds($subscription);
        }
    }

    /**
     * Auto-boost all ads for this agency
     */
    private function boostAllAgencyAds(Subscription $subscription): void
    {
        Ad::where('agency_id', $subscription->agency_id)
            ->whereIn('status', ['available', 'reserved'])
            ->where('is_visible', true)
            ->chunk(100, function ($ads) use ($subscription) {
                foreach ($ads as $ad) {
                    $ad->forceFill([
                        'is_subscription_sponsored' => true,
                        'subscription_tier' => 'subscription_sponsored',
                        'boost_score' => $ad->computeRankingScore(),
                    ])->save();
                }
            });
    }

    /**
     * Remove auto-boost from all agency ads
     */
    private function unboostAllAgencyAds(Subscription $subscription): void
    {
        Ad::where('agency_id', $subscription->agency_id)
            ->where('is_subscription_sponsored', true)
            ->chunk(100, function ($ads) {
                foreach ($ads as $ad) {
                    $ad->forceFill([
                        'is_subscription_sponsored' => false,
                        'subscription_tier' => null,
                        'boost_score' => $ad->computeRankingScore(),
                    ])->save();
                }
            });
    }
}
```

#### 4. Ad Observer (Auto-Sponsor New Ads)

```php
// app/Observers/AdObserver.php (add to existing observer)

public function created(Ad $ad): void
{
    // Existing logic...

    // Auto-sponsor if agency has active subscription
    if ($ad->agency_id) {
        $hasActiveSubscription = $ad->agency
            ->subscriptions()
            ->where('status', SubscriptionStatus::ACTIVE)
            ->where('ends_at', '>', now())
            ->exists();

        if ($hasActiveSubscription) {
            $ad->forceFill([
                'is_subscription_sponsored' => true,
                'subscription_tier' => 'subscription_sponsored',
                'boost_score' => $ad->computeRankingScore(),
            ])->saveQuietly();
        }
    }
}
```

#### 5. Feed Controller (Interleaved Ranking)

```php
// app/Http/Controllers/Api/V1/Ad/AdSearchController.php

public function search(AdRequest $request): JsonResponse
{
    // ... existing validation ...

    // Separate queries for each tier
    $premiumSponsored = Ad::where('is_subscription_sponsored', true)
        ->where('is_boosted', true)
        ->where('last_shown_at', '<', now()->subMinutes(30)) // rotation
        ->orderByDesc('boost_score')
        ->limit(3)
        ->get();

    $subscriptionSponsored = Ad::where('is_subscription_sponsored', true)
        ->where('is_boosted', false)
        ->where('last_shown_at', '<', now()->subMinutes(20))
        ->orderByDesc('boost_score')
        ->limit(4)
        ->get();

    $organic = Ad::where('is_subscription_sponsored', false)
        ->where('is_boosted', false)
        ->orderByDesc('reviews_avg_rating')
        ->orderByDesc('created_at')
        ->limit(3)
        ->get();

    // Interleave: Premium → Subscription → Organic pattern
    $interleavedAds = collect();
    $interleavedAds = $interleavedAds
        ->concat($premiumSponsored->take(3))
        ->push($subscriptionSponsored->shift())
        ->push($organic->shift())
        ->concat($subscriptionSponsored->take(2))
        ->push($organic->shift())
        ->concat($organic);

    // Record impressions for rotation
    foreach ($interleavedAds as $ad) {
        $ad->recordImpression();
    }

    return AdApiResource::collection($interleavedAds);
}
```

### Frontend Integration

#### Ad Card Badge Component

```tsx
// keyhome-frontend-next/src/components/ads/AdSponsorBadge.tsx

interface Props {
  tier: 'premium_sponsored' | 'subscription_sponsored' | 'manual_boost' | 'organic';
}

export default function AdSponsorBadge({ tier }: Props) {
  if (tier === 'organic') return null;

  const config = {
    premium_sponsored: {
      icon: '⭐',
      label: 'Premium Sponsored',
      color: 'linear-gradient(135deg, #FFD700, #FFA500)',
    },
    subscription_sponsored: {
      icon: '✨',
      label: 'Sponsored',
      color: 'linear-gradient(135deg, #E8304A, #FF6B9D)',
    },
    manual_boost: {
      icon: '🚀',
      label: 'Boosted',
      color: 'linear-gradient(135deg, #667EEA, #764BA2)',
    },
  }[tier];

  return (
    <Box
      sx={{
        position: 'absolute',
        top: 8,
        left: 8,
        background: config.color,
        color: 'white',
        fontSize: '0.7rem',
        fontWeight: 700,
        px: 1,
        py: 0.5,
        borderRadius: 1,
        display: 'flex',
        alignItems: 'center',
        gap: 0.5,
        boxShadow: '0 2px 8px rgba(0,0,0,0.15)',
      }}
    >
      <span>{config.icon}</span>
      <span>{config.label}</span>
    </Box>
  );
}
```

### Analytics & Monitoring

#### Metrics to Track

1. **Subscription Impact**
   - Average position of sponsored vs organic ads
   - CTR difference between sponsored/organic
   - Conversion rate (contact/unlock) by tier

2. **User Experience**
   - Session duration
   - Scroll depth
   - Bounce rate on search results
   - "Hide this ad" frequency (ad fatigue indicator)

3. **Revenue Metrics**
   - Subscription conversion rate
   - LTV of sponsored ad owners
   - Manual boost purchase rate (stacking behavior)

4. **Feed Health**
   - Sponsored ad saturation (target: 60%)
   - Organic ad visibility
   - Ad diversity (avoid same advertiser dominating)

### Business Rules

#### Fair Play Policies

1. **Frequency Capping**: Same advertiser's ads shown max 3 times per 10 results
2. **Rotation**: Sponsored ads rotate every 30-60 minutes
3. **Quality Threshold**: Ads with < 2.0 rating excluded from top positions
4. **Geographic Relevance**: User's location boosts nearby ads
5. **Fresh Content**: Ads older than 90 days get 20% score reduction

#### Edge Cases

**Case 1**: Agency has 100 ads, all subscription-sponsored
- **Solution**: Rotate based on performance + freshness + user's past interactions

**Case 2**: No organic ads available (all sponsored)
- **Solution**: Disable interleaving, show all sponsored with diversity rules

**Case 3**: Subscription expires mid-day
- **Solution**: Graceful degradation - ads remain visible but drop to organic tier over 24h

**Case 4**: Manual boost added to subscription-sponsored ad
- **Solution**: Immediate upgrade to Tier 1 (Premium Sponsored)

### Migration Plan

#### Phase 1: Add Subscription Tracking (Week 1)
- Add `is_subscription_sponsored` column
- Add `subscription_tier` column
- Add `last_shown_at` column
- Add `impression_count` column
- Create indexes

#### Phase 2: Implement Auto-Boost Logic (Week 2)
- Create `SubscriptionObserver`
- Update `AdObserver`
- Test auto-boost on subscription activation
- Test auto-unboost on expiration

#### Phase 3: Update Ranking Algorithm (Week 3)
- Implement `getSponsorshipTier()`
- Implement `computeRankingScore()` with multipliers
- Add time decay logic
- Add impression tracking

#### Phase 4: Frontend Integration (Week 4)
- Add `AdSponsorBadge` component
- Update `AdCard` to show tier
- Add "Sponsored" labels to Meilisearch results
- Update API resources to include `sponsorship_tier`

#### Phase 5: Analytics & Optimization (Week 5+)
- Deploy to production
- Monitor metrics
- A/B test interleaving ratios
- Adjust multipliers based on data

### Testing Strategy

#### Unit Tests
```php
// tests/Unit/AdSponsorshipTest.php

it('detects subscription-sponsored ads', function () {
    $agency = Agency::factory()->create();
    $subscription = Subscription::factory()->active()->create(['agency_id' => $agency->id]);
    $ad = Ad::factory()->create(['agency_id' => $agency->id]);

    expect($ad->isSubscriptionSponsored())->toBeTrue();
});

it('calculates premium tier for subscription + boost', function () {
    $ad = Ad::factory()
        ->subscriptionSponsored()
        ->boosted()
        ->create();

    expect($ad->getSponsorshipTier())->toBe('premium_sponsored');
});

it('computes higher score for sponsored ads', function () {
    $organicAd = Ad::factory()->create();
    $sponsoredAd = Ad::factory()->subscriptionSponsored()->create();

    expect($sponsoredAd->computeRankingScore())
        ->toBeGreaterThan($organicAd->computeRankingScore());
});
```

#### Integration Tests
```php
// tests/Feature/FeedRankingTest.php

it('prioritizes premium sponsored ads in feed', function () {
    Ad::factory()->count(5)->create(); // organic
    Ad::factory()->count(3)->subscriptionSponsored()->create();
    Ad::factory()->count(2)->premiumSponsored()->create();

    $response = $this->getJson('/api/v1/ads/search');

    $response->assertOk();
    $firstThreeAds = array_slice($response->json('data'), 0, 3);

    foreach ($firstThreeAds as $ad) {
        expect($ad['sponsorship_tier'])->toBe('premium_sponsored');
    }
});

it('rotates sponsored ads based on impressions', function () {
    $ad = Ad::factory()->subscriptionSponsored()->create();

    $ad->update(['last_shown_at' => now()->subMinutes(5)]);
    $response1 = $this->getJson('/api/v1/ads/search');
    $position1 = collect($response1->json('data'))->search(fn($a) => $a['id'] === $ad->id);

    $ad->update(['last_shown_at' => now()]);
    $response2 = $this->getJson('/api/v1/ads/search');
    $position2 = collect($response2->json('data'))->search(fn($a) => $a['id'] === $ad->id);

    expect($position2)->toBeGreaterThan($position1); // Lower position after recent impression
});
```

## Rollback Plan

If issues arise:
1. **Disable subscription auto-boost**: Set `is_subscription_sponsored = FALSE` for all ads
2. **Revert to manual boost only**: Remove tier multipliers, use existing 1.5× boost
3. **Database rollback**: Drop new columns if migration needed

## Next Steps

1. Review business requirements with stakeholders
2. Finalize tier multipliers (2.5×, 1.8×, 1.5×)
3. Decide on interleaving ratio (currently 6/10 sponsored)
4. Implement Phase 1 (database changes)
5. Create tasks for Phases 2-5

---

**Status**: 📝 Design Document  
**Target Start**: After approval  
**Estimated Duration**: 5 weeks  
**Risk Level**: Medium (impacts core search ranking)
