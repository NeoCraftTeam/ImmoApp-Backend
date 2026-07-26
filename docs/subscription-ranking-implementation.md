# Subscription-Based Ad Ranking Implementation

## Overview

KeyHome now features a Facebook Ads-style subscription system where agencies with active subscriptions automatically have all their ads prioritized in the feed. This creates a premium advertising tier that ensures maximum visibility for subscribed agencies.

## Architecture

### Database Schema

**Migration**: `2026_05_29_100115_add_subscription_sponsorship_to_ads_table`

New columns added to `ad` table:
- `is_subscription_sponsored` (boolean, default false): Indicates if ad is sponsored via agency subscription
- `subscription_tier` (string, nullable): Tier identifier for the sponsorship level
- `last_shown_at` (timestamp, nullable): Last time ad was shown to users (for rotation)
- `impression_count` (unsigned integer, default 0): Total impressions counter

**Indexes**:
- Composite index: `idx_ad_feed_ranking` on `(is_subscription_sponsored, boost_score, created_at, status, is_visible)`
- Single index: `idx_ad_last_shown` on `last_shown_at`

### Ranking Tiers

The system implements a 4-tier ranking multiplier:

1. **Subscription Sponsored** (2.5×): Ads from agencies with active subscriptions
2. **Point Boosted** (1.8×): Ads with active point-based boosts (`is_boosted=true` and unexpired)
3. **Boost Score** (1.5×): Ads with boost_score > 0 (from past boosts or promotions)
4. **Organic** (1.0×): Regular ads with no boost

### Ranking Formula

```php
final_score = base_score × tier_multiplier × time_decay × rotation_penalty
```

Where:
- **base_score**: `max(1, boost_score ?? 0)` - ensures minimum score of 1
- **tier_multiplier**: From `getSponsorshipTier()` - 2.5×, 1.8×, 1.5×, or 1.0×
- **time_decay**: `max(0.1, 1 - (age_in_days / 100))` - reduces 1% per day
- **rotation_penalty**: 0.7× if shown in last 6 hours, otherwise 1.0×

### Ordering Logic

Feed ads are ordered by:
1. `is_subscription_sponsored DESC` - Subscription-sponsored first
2. `boost_score DESC` - Higher boost scores first within same tier
3. `created_at DESC` - Newer ads first for same boost level
4. `id DESC` - Stable tie-breaker

## Implementation Details

### Automatic Sponsorship

**AdObserver** (`app/Observers/AdObserver.php`):
- When a new ad is created, checks if the agency has an active subscription
- Auto-sponsors the ad if subscription is active

**SubscriptionObserver** (`app/Observers/SubscriptionObserver.php`):
- When subscription becomes ACTIVE: Bulk-sponsors all agency ads
- When subscription EXPIRES or CANCELLED: Bulk-unsponsors all agency ads
- Logs all sponsorship changes for audit trail

### Model Methods

**Ad Model** (`app/Models/Ad.php`):

```php
// Get sponsorship tier multiplier
public function getSponsorshipTier(): float

// Compute final ranking score with decay and penalties
public function computeRankingScore(): float

// Record impression for analytics
public function recordImpression(): void

// Scope for sponsorship-based ordering
public function scopeOrderBySponsorship($query)
```

### API Integration

**AdResource** (`app/Http/Resources/AdResource.php`):
- Includes `is_subscription_sponsored` (boolean)
- Includes `sponsorship_tier` (float) - the multiplier value
- Both fields are public-visible for rendering sponsor badges

**AdController** (`app/Http/Controllers/Api/V1/Ad/AdController.php`):
- Feed endpoint uses `orderBySponsorship()` scope
- Works with cursor pagination
- Cache invalidation on ad changes

## Cache Strategy

**Feed Cache**:
- Guest first-page requests cached for 5 minutes
- Cache key pattern: `ads:feed:guest:first:pp={perPage}`
- Automatically invalidated when ads are created/updated/deleted

**AdObserver cache invalidation**:
```php
private function invalidateFeedCache(): void
{
    foreach ([15, 20, 30, 50] as $perPage) {
        Cache::forget("ads:feed:guest:first:pp={$perPage}");
    }
    Cache::forget('reco_v2_guest');
}
```

## Testing

### Test Coverage

**SubscriptionAdSponsorshipTest** (5 tests):
- Auto-sponsor on subscription activation
- Auto-unsponsor on subscription expiration/cancellation  
- New ad auto-sponsorship for subscribed agencies

**AdRankingAlgorithmTest** (10 tests):
- Tier multiplier calculations for all 4 tiers
- Time decay algorithm
- Rotation penalty
- Direct query ordering
- Impression tracking

**AdSponsorshipScopeTest** (4 tests):
- Subscription-first ordering
- Boost score secondary ordering
- Created_at tie-breaker
- Cursor pagination compatibility

Total: **19 tests, 47 assertions**

### Running Tests

```bash
# All subscription ranking tests
php artisan test --filter=Subscription

# All ranking algorithm tests
php artisan test --filter=Ranking

# All scope tests
php artisan test tests/Feature/AdSponsorshipScopeTest.php

# Complete suite
php artisan test tests/Feature/SubscriptionAdSponsorshipTest.php \
                 tests/Feature/AdRankingAlgorithmTest.php \
                 tests/Feature/AdSponsorshipScopeTest.php
```

## Usage Examples

### Frontend Badge Rendering

```typescript
// API response includes sponsorship info
{
  "id": "...",
  "title": "...",
  "is_subscription_sponsored": true,
  "sponsorship_tier": 2.5,
  "boost_score": 50,
  // ... other fields
}

// Render badge based on sponsorship
{ad.is_subscription_sponsored && (
  <Badge variant="premium">Sponsorisé</Badge>
)}
```

### Checking Ad Sponsorship Status

```php
$ad = Ad::find($id);

// Get tier multiplier
$tier = $ad->getSponsorshipTier(); // 2.5, 1.8, 1.5, or 1.0

// Compute full ranking score
$score = $ad->computeRankingScore();

// Record impression
$ad->recordImpression();
```

### Manual Sponsorship Control

```php
// Manually sponsor an ad
$ad->update([
    'is_subscription_sponsored' => true,
    'subscription_tier' => 'subscription_sponsored',
]);

// Remove sponsorship
$ad->update([
    'is_subscription_sponsored' => false,
    'subscription_tier' => null,
]);
```

## Performance Considerations

### Query Optimization

The composite index `idx_ad_feed_ranking` ensures feed queries are fast:
```sql
-- Optimized by index
SELECT * FROM ad 
WHERE is_visible = true 
  AND status IN ('available', 'reserved')
ORDER BY is_subscription_sponsored DESC, 
         boost_score DESC, 
         created_at DESC, 
         id DESC
LIMIT 50;
```

### Expected Performance

- Feed queries: < 50ms for 50 results
- Auto-sponsor bulk update: < 100ms for 100 ads
- Cache hit rate: ~80% for guest users

### Monitoring

Track these metrics:
- `impression_count`: Total impressions per ad
- `last_shown_at`: Distribution of ad visibility
- Cache hit rate: `ads:feed:guest:first:pp=*`
- Sponsorship conversion: % of subscribed agency ads

## Business Logic

### Subscription Lifecycle

1. **Agency purchases subscription** → Subscription status = ACTIVE
2. **SubscriptionObserver.created()** → All agency ads sponsored
3. **New ads created** → AdObserver checks subscription → Auto-sponsor
4. **Subscription expires** → Subscription status = EXPIRED
5. **SubscriptionObserver.updated()** → All agency ads unsponosred

### Fair Distribution

While subscription-sponsored ads appear first, the ranking formula ensures:
- Time decay prevents old sponsored ads from dominating
- Rotation penalty reduces ad fatigue
- Within sponsored tier, organic competition remains (boost_score still matters)
- Organic ads with very high boost_score can still compete

### Feed Composition Target

Ideal feed distribution (not enforced, emerges from ranking):
- 60% subscription-sponsored ads
- 25% point-boosted ads
- 15% organic ads

## Migration Path

### Backfilling Existing Data

If agencies have pre-existing subscriptions:

```php
// Run once after migration
Artisan::command('backfill:subscription-sponsorship', function () {
    $activeSubscriptions = Subscription::where('status', SubscriptionStatus::ACTIVE)
        ->where('ends_at', '>', now())
        ->get();

    foreach ($activeSubscriptions as $subscription) {
        $count = Ad::where('agency_id', $subscription->agency_id)
            ->whereIn('status', ['available', 'reserved'])
            ->where('is_visible', true)
            ->update([
                'is_subscription_sponsored' => true,
                'subscription_tier' => 'subscription_sponsored',
            ]);

        $this->info("Sponsored {$count} ads for agency {$subscription->agency_id}");
    }
});
```

### Rollback Plan

To revert if needed:

```php
// Remove sponsorship from all ads
DB::table('ad')->update([
    'is_subscription_sponsored' => false,
    'subscription_tier' => null,
]);

// Rollback migration
php artisan migrate:rollback --step=1
```

## Future Enhancements

### Potential Additions

1. **Multiple Subscription Tiers**: Premium (3.0×), Standard (2.5×), Basic (2.0×)
2. **Geographic Targeting**: Subscription sponsorship limited to specific cities
3. **Time-based Campaigns**: Sponsor only during specific hours/days
4. **Budget Caps**: Max impressions per day for sponsored ads
5. **A/B Testing**: Test different multipliers for conversion optimization
6. **Analytics Dashboard**: Real-time sponsorship ROI tracking

### API Extensions

```php
// Future: Subscription tier selection
POST /api/v1/agencies/{agency}/subscription
{
  "plan_id": "premium",
  "tier_multiplier": 3.0,
  "duration_months": 12
}

// Future: Impression analytics
GET /api/v1/my/ads/{ad}/sponsorship-analytics
{
  "impressions_today": 1234,
  "ctr": 0.034,
  "average_position": 2.3,
  "estimated_value": "45,600 FCFA"
}
```

## Troubleshooting

### Common Issues

**Issue**: Ads not appearing as sponsored after subscription activation
- Check subscription status is ACTIVE
- Verify `ends_at` is in the future
- Check `agency_id` matches between subscription and ads
- Review logs for observer execution

**Issue**: Feed order seems wrong
- Clear cache: `php artisan cache:clear`
- Check database: `SELECT id, is_subscription_sponsored, boost_score FROM ad LIMIT 10;`
- Verify observer is registered in `ObserverServiceProvider`

**Issue**: Performance degradation
- Check index usage: `EXPLAIN SELECT * FROM ad WHERE is_visible = true ORDER BY is_subscription_sponsored DESC;`
- Monitor cache hit rate
- Consider pagination page size (default 15, max 50)

## References

- [Subscription Model](../app/Models/Subscription.php)
- [Ad Model](../app/Models/Ad.php)
- [Subscription Observer](../app/Observers/SubscriptionObserver.php)
- [Ad Observer](../app/Observers/AdObserver.php)
- [Migration](../database/migrations/2026_05_29_100115_add_subscription_sponsorship_to_ads_table.php)
