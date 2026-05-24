<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\AdInteractionController;
use App\Http\Controllers\Api\V1\AdTypeController;
use App\Http\Controllers\Api\V1\AgencyController;
use App\Http\Controllers\Api\V1\BailleurFollowController;
use App\Http\Controllers\Api\V1\BoostController;
use App\Http\Controllers\Api\V1\BulkAdController;
use App\Http\Controllers\Api\V1\ChatE2eeIdentityController;
use App\Http\Controllers\Api\V1\CityController;
use App\Http\Controllers\Api\V1\ClerkWebhookController;
use App\Http\Controllers\Api\V1\ConversationController;
use App\Http\Controllers\Api\V1\CookieConsentController;
use App\Http\Controllers\Api\V1\DocumentController;
use App\Http\Controllers\Api\V1\DuplicateAdController;
use App\Http\Controllers\Api\V1\ExpenseController;
use App\Http\Controllers\Api\V1\FcmTokenController;
use App\Http\Controllers\Api\V1\GdprController;
use App\Http\Controllers\Api\V1\HealthCheckController;
use App\Http\Controllers\Api\V1\LeaseContractController;
use App\Http\Controllers\Api\V1\LoginHistoryController;
use App\Http\Controllers\Api\V1\MessageController;
use App\Http\Controllers\Api\V1\MyReviewsController;
use App\Http\Controllers\Api\V1\NaturalSearchController;
use App\Http\Controllers\Api\V1\NewsletterController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\NotificationPreferenceController;
use App\Http\Controllers\Api\V1\OwnerDashboardController;
use App\Http\Controllers\Api\V1\PriceHeatmapController;
use App\Http\Controllers\Api\V1\PropertyAttributeController;
use App\Http\Controllers\Api\V1\PwaController;
use App\Http\Controllers\Api\V1\QrCodeController;
use App\Http\Controllers\Api\V1\QuarterController;
use App\Http\Controllers\Api\V1\RecommendationController;
use App\Http\Controllers\Api\V1\RentEstimatorController;
use App\Http\Controllers\Api\V1\SearchAlertController;
use App\Http\Controllers\Api\V1\SignatureController;
use App\Http\Controllers\Api\V1\StatsController;
use App\Http\Controllers\Api\V1\TeamController;
use App\Http\Controllers\Api\V1\TenantController;
use App\Http\Controllers\Api\V1\TrustScoreController;
use App\Http\Controllers\Api\V1\TurnstilePublicConfigController;
use App\Http\Controllers\Api\V1\UserController;
use App\Http\Controllers\Api\V1\VisitTrackingController;
use App\Models\AdType;
use App\Models\Agency;
use App\Models\City;
use App\Models\Quarter;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Route;

// Public liveness probe — no auth, no infra details. Used by CI smoke tests and uptime monitors.
Route::get('/ping', fn () => response()->json(['status' => 'ok'], 200));

// Comprehensive health check — DB · Redis · Queue · Storage · Meilisearch · Flutterwave
// Auth: optional static bearer token via HEALTH_CHECK_TOKEN env var (see HealthCheckController).
// Use ?force=true to bypass the 30-second result cache.
Route::get('/health', HealthCheckController::class)
    ->middleware('throttle:30,1');

// Prefix routes
Route::prefix('v1')->group(function (): void {

    Route::get('/config/turnstile', TurnstilePublicConfigController::class)
        ->middleware('throttle:120,1');

    // Domain route files
    require __DIR__.'/api/auth.php';
    require __DIR__.'/api/ads.php';
    require __DIR__.'/api/payments.php';
    require __DIR__.'/api/viewings.php';
    require __DIR__.'/api/surveys.php';
    require __DIR__.'/api/geo.php';

    // --- AD TYPES ---
    Route::controller(AdTypeController::class)->group(function (): void {
        Route::get('/ad-types', 'index')->middleware('cdn.cache:3600');
        Route::get('/ad-types/{adType}', 'show')->middleware('cdn.cache:3600');
    });
    // Admin write actions: enforce MFA when admin has TOTP/email MFA configured.
    Route::middleware(['auth:sanctum', 'mfa.admin'])->controller(AdTypeController::class)->group(function (): void {
        Route::post('/ad-types', 'store')->can('create', AdType::class);
        Route::put('/ad-types/{adType}', 'update')->can('update', 'adType');
        Route::delete('/ad-types/{adType}', 'destroy')->can('delete', 'adType');
    });

    // --- CITIES ---
    Route::controller(CityController::class)->group(function (): void {
        Route::get('/cities', 'index')->middleware('cdn.cache:3600');
        Route::get('/cities/{id}', 'show')->middleware('cdn.cache:3600');
        Route::post('/cities', 'store')->middleware(['auth:sanctum', 'mfa.admin'])->can('create', City::class);
        Route::put('/cities/{city}', 'update')->middleware(['auth:sanctum', 'mfa.admin'])->can('update', 'city');
        Route::delete('/cities/{city}', 'destroy')->middleware(['auth:sanctum', 'mfa.admin'])->can('delete', 'city');
    });

    // --- QUARTERS ---
    Route::controller(QuarterController::class)->group(function (): void {
        Route::get('/quarters', 'index')->middleware('cdn.cache:3600');
        Route::get('/quarters/{id}', 'show')->middleware('cdn.cache:3600');
        Route::post('/quarters', 'store')->middleware(['auth:sanctum', 'mfa.admin'])->can('create', Quarter::class);
        Route::put('/quarters/{quarter}', 'update')->middleware(['auth:sanctum', 'mfa.admin'])->can('update', 'quarter');
        Route::delete('/quarters/{quarter}', 'destroy')->middleware(['auth:sanctum', 'mfa.admin'])->can('delete', 'quarter');
    });

    // --- AGENCIES ---
    Route::controller(AgencyController::class)->group(function (): void {
        Route::get('/agencies', 'index');
        Route::get('/agencies/{agency}', 'show');
        Route::post('/agencies', 'store')->middleware('auth:sanctum')->can('create', Agency::class);
        Route::put('/agencies/{agency}', 'update')->middleware('auth:sanctum')->can('update', 'agency');
        Route::delete('/agencies/{agency}', 'destroy')->middleware('auth:sanctum')->can('delete', 'agency');
    });

    // --- USERS ---
    Route::get('/users/{identifier}/public-profile', [UserController::class, 'publicProfile'])
        ->middleware('throttle:60,1');
    Route::middleware('auth:sanctum')->controller(UserController::class)->group(function (): void {
        // Read endpoints — admin listing + show — also gated by MFA when admin has it set up.
        Route::get('/users', 'index')->middleware('mfa.admin')->can('viewAny', User::class);
        Route::get('/users/{id}', 'show');
        // Mutating endpoints — gated for admin (and harmless for non-admin since `mfa.admin` is a no-op there).
        Route::post('/users', 'store')->middleware('mfa.admin')->can('create', User::class);
        // OWASP A05 — align with POST /users and DELETE /users/{user} which
        // already enforce `mfa.admin`. An admin update can change a user's
        // role or email, so it carries the same impact as create/destroy
        // and must gate behind the same MFA bar.
        Route::put('/users/{user}', 'update')->middleware('mfa.admin');
        Route::delete('/users/{user}', 'destroy')->middleware('mfa.admin');
    });

    // --- BAILLEUR FOLLOW ---
    // Status: optional auth — guests get following=false, authenticated users get their real state.
    Route::get('/bailleurs/{username}/follow', [BailleurFollowController::class, 'status'])
        ->middleware(['optional.auth', 'throttle:60,1']);
    // Toggle: requires auth.
    Route::post('/bailleurs/{username}/follow', [BailleurFollowController::class, 'toggle'])
        ->middleware(['auth:sanctum', 'throttle:30,1']);

    // --- RECOMMENDATIONS ---
    // Server caches per-user/guest results 10 min (RecommendationEngine::CACHE_TTL_MINUTES).
    // CDN cache only kicks in for guests (CdnCache short-circuits when $request->user() is set).
    Route::middleware(['optional.auth', 'cdn.cache:600'])->get('/recommendations', [RecommendationController::class, 'index']);

    // --- MY UNLOCKED ADS ---
    Route::middleware('auth:sanctum')->get('/my/unlocked-ads', [UserController::class, 'unlockedAds']);

    // --- TRUST SCORE ---
    Route::get('/users/{user}/trust-score', [TrustScoreController::class, 'show'])
        ->middleware('throttle:60,1');
    Route::middleware('auth:sanctum')->group(function (): void {
        Route::get('/my/trust-score', [TrustScoreController::class, 'me']);
        Route::post('/my/trust-score/consent', [TrustScoreController::class, 'consent'])
            ->middleware('throttle:10,1');
    });

    // --- Cookie consent logging (CNIL Art. 5-1-a — proof of consent) ---
    // Open to everyone (including anonymous visitors). auth:sanctum is optional
    // so the controller can attach user_id for authenticated users.
    Route::post('/consent/cookies', CookieConsentController::class)
        ->middleware('throttle:30,1');

    // --- GDPR Data Export & Account Deletion ---
    Route::middleware('auth:sanctum')->group(function (): void {
        Route::get('/my/data-export', [GdprController::class, 'export'])
            ->middleware('throttle:5,1');
        Route::delete('/my/account', [GdprController::class, 'deleteAccount'])
            ->middleware('throttle:3,1');
    });

    // --- MY FAVORITES / RECENTLY VIEWED ---
    Route::middleware('auth:sanctum')->get('/my/favorites', [AdInteractionController::class, 'favorites']);
    Route::middleware('auth:sanctum')->get('/my/recently-viewed', [AdInteractionController::class, 'recentlyViewed']);

    // --- NOTIFICATIONS ---
    Route::middleware('auth:sanctum')->prefix('notifications')->controller(NotificationController::class)->group(function (): void {
        Route::get('/', 'index');
        Route::get('/unread-count', 'unreadCount');
        Route::post('/read-all', 'markAllAsRead');
        Route::post('/{id}/read', 'markAsRead');
        Route::delete('/{id}', 'destroy');
    });

    // --- PROPERTY ATTRIBUTES (public) ---
    Route::get('/property-attributes', [PropertyAttributeController::class, 'index'])->middleware('cdn.cache:1800');

    // --- VISIT TRACKING (anonymous) ---
    Route::post('/track/visit', [VisitTrackingController::class, 'store'])
        ->middleware('throttle:60,1');

    // --- PUBLIC STATS (W37: extracted from inline closures to StatsController) ---
    Route::controller(StatsController::class)->middleware('throttle:30,1')->group(function (): void {
        Route::get('/stats/landing', 'landing')->name('stats.landing')->middleware('cdn.cache:300');
        Route::get('/stats/testimonials', 'testimonials')->name('stats.testimonials')->middleware('cdn.cache:3600');
    });

    // --- CLERK WEBHOOKS ---
    Route::post('/clerk/webhook', [ClerkWebhookController::class, 'handle'])
        ->middleware('throttle:60,1');

    // --- LEASE CONTRACTS (landlord) ---
    Route::middleware(['auth:sanctum', 'owner.role'])->prefix('my/lease-contracts')->controller(LeaseContractController::class)->group(function (): void {
        Route::get('/', 'index');
        // OWASP A01 / LLM10 — gate the AI lease-conditions enhancer to
        // landlords (AGENT/ADMIN). The endpoint has no per-record context
        // (only a free-text payload), so customer accounts have no
        // legitimate reason to call it. Throttle keeps abuse + cost
        // bounded even for legitimate agents.
        Route::post('/ai/enhance-conditions', 'enhanceConditions')
            ->middleware('throttle:10,1');
        Route::post('/ai/summarize', 'summarize')
            ->middleware('throttle:10,1');
        Route::get('/{leaseContract}', 'show')->name('lease-contracts.show');
        Route::put('/{leaseContract}', 'update')->name('lease-contracts.update');
        Route::post('/{ad}/generate', 'store')->name('lease-contracts.generate');
        Route::get('/{leaseContract}/download', 'download')->name('lease-contracts.download');
        Route::get('/{leaseContract}/audit-log', 'auditLog')->name('lease-contracts.audit-log');
    });

    // --- OWNER DASHBOARD STATS (Audit Item 9: occupancy rate, boosts, viewings, messages) ---
    Route::middleware(['auth:sanctum', 'owner.role'])
        ->get('/my/stats', [OwnerDashboardController::class, 'stats'])
        ->name('owner.stats')
        ->middleware('throttle:60,1');

    // --- MY REVIEWS (landlord — reviews on my ads) ---
    Route::middleware('auth:sanctum')->get('/my/reviews', [MyReviewsController::class, 'index']);

    // --- QR & printables (landlord profile + per-ad assets) ---
    Route::middleware(['auth:sanctum', 'owner.role', 'panel.role:owner', 'token.role:agent'])
        ->prefix('my/profile')
        ->controller(QrCodeController::class)
        ->group(function (): void {
            Route::get('/qr-code', 'profileMeta')->middleware('throttle:60,1');
            Route::get('/qr-code/image', 'profileQrImage')->middleware('throttle:60,1');
            Route::get('/business-card', 'businessCard')->middleware('throttle:20,1');
            Route::get('/business-card/preview', 'businessCardPreview')->middleware('throttle:20,1');
            Route::get('/placarde', 'profilePlacarde')->middleware('throttle:20,1');
        });

    Route::middleware(['auth:sanctum', 'owner.role', 'panel.role:owner', 'token.role:agent'])
        ->prefix('my/ads/{ad}')
        ->controller(QrCodeController::class)
        ->group(function (): void {
            Route::get('/qr-code', 'adMeta')->middleware('throttle:60,1');
            Route::get('/qr-code/image', 'adQrImage')->middleware('throttle:60,1');
            Route::get('/placarde', 'adPlacarde')->middleware('throttle:20,1');
            Route::get('/placarde/preview', 'adPlacardePreview')->middleware('throttle:20,1');
        });

    // --- PWA (Push Subscriptions & Session Validation) ---
    Route::prefix('pwa')->middleware('web')->group(function (): void {
        Route::middleware('auth:web,sanctum')->group(function (): void {
            Route::post('/push/subscribe', [PwaController::class, 'subscribe']);
            Route::post('/push/unsubscribe', [PwaController::class, 'unsubscribe']);
        });
        Route::get('/session/validate', [PwaController::class, 'validateSession']);
    });

    // --- Push (SPA / Bearer token — for Next.js frontend) ---
    Route::middleware('auth:sanctum')->group(function (): void {
        Route::post('/push/subscribe', [PwaController::class, 'subscribe']);
        Route::post('/push/unsubscribe', [PwaController::class, 'unsubscribe']);
    });

    // --- SEARCH ALERTS ---
    Route::middleware('auth:sanctum')->prefix('search-alerts')->group(function (): void {
        Route::get('/', [SearchAlertController::class, 'index']);
        Route::post('/', [SearchAlertController::class, 'store'])->middleware('throttle:20,1');
        Route::put('/{searchAlert}', [SearchAlertController::class, 'update']);
        Route::delete('/{searchAlert}', [SearchAlertController::class, 'destroy']);
    });

    // --- RENT ESTIMATOR (public) ---
    Route::get('/rent-estimate', [RentEstimatorController::class, 'estimate'])
        ->middleware(['throttle:30,1', 'cdn.cache:600']);

    // --- PRICE HEATMAP (public) ---
    Route::get('/price-heatmap', [PriceHeatmapController::class, 'index'])
        ->middleware(['throttle:30,1', 'cdn.cache:1800']);

    // --- NATURAL LANGUAGE SEARCH ---
    Route::post('/search/parse', [NaturalSearchController::class, 'parse'])
        ->middleware('throttle:30,1');

    // --- NEWSLETTER ---
    Route::post('/newsletter/subscribe', [NewsletterController::class, 'subscribe'])
        ->middleware('throttle:5,10');
    Route::get('/newsletter/unsubscribe/{token}', [NewsletterController::class, 'unsubscribe']);

    // --- BOOST ---
    Route::get('/boost-packs', [BoostController::class, 'packs']); // public: browse packs
    Route::middleware('auth:sanctum')->group(function (): void {
        Route::get('/my/ads/{ad}/boost-status', [BoostController::class, 'status']);
        Route::post('/my/ads/{ad}/boost', [BoostController::class, 'boost'])->middleware('throttle:10,1');
        Route::delete('/my/ads/{ad}/boost', [BoostController::class, 'unboost']);
        Route::get('/my/ads/{ad}/boost/roi', [BoostController::class, 'boostRoi'])->name('boost.roi');
        Route::post('/my/ads/{ad}/duplicate', [DuplicateAdController::class, 'store']);
        Route::put('/my/ads/bulk-update', [BulkAdController::class, 'bulkUpdate']);
        Route::post('/my/ads/bulk-delete', [BulkAdController::class, 'bulkDelete']);
    });

    // --- TENANTS (owner) ---
    Route::middleware('auth:sanctum')->prefix('my/tenants')->controller(TenantController::class)->group(function (): void {
        Route::get('/', 'index');
        Route::post('/', 'store')->middleware('throttle:30,1');
        Route::get('/{tenant}', 'show');
        Route::put('/{tenant}', 'update');
        Route::delete('/{tenant}', 'destroy');
    });

    // --- EXPENSES (owner, per property) ---
    Route::middleware('auth:sanctum')->group(function (): void {
        Route::get('/my/ads/{ad}/expenses', [ExpenseController::class, 'index']);
        Route::post('/my/ads/{ad}/expenses', [ExpenseController::class, 'store'])->middleware('throttle:30,1');
        Route::get('/my/ads/{ad}/profit-loss', [ExpenseController::class, 'profitLoss']);
        Route::delete('/my/expenses/{expense}', [ExpenseController::class, 'destroy']);
    });

    // --- DOCUMENTS (owner, per property) ---
    Route::middleware('auth:sanctum')->group(function (): void {
        Route::get('/my/ads/{ad}/documents', [DocumentController::class, 'index']);
        Route::post('/my/ads/{ad}/documents', [DocumentController::class, 'store'])->middleware('throttle:20,1');
        Route::get('/my/documents/{document}/download', [DocumentController::class, 'download']);
        Route::delete('/my/documents/{document}', [DocumentController::class, 'destroy']);
    });

    // --- NOTIFICATION PREFERENCES (owner) ---
    Route::middleware('auth:sanctum')->group(function (): void {
        Route::get('/my/notification-preferences', [NotificationPreferenceController::class, 'show']);
        Route::put('/my/notification-preferences', [NotificationPreferenceController::class, 'update']);
    });

    // --- LOGIN HISTORY (owner) ---
    Route::middleware('auth:sanctum')->group(function (): void {
        Route::get('/my/login-history', [LoginHistoryController::class, 'index']);
        Route::delete('/my/login-history', [LoginHistoryController::class, 'destroy']);
    });

    // --- TEAM MANAGEMENT (agency owners) ---
    Route::middleware('auth:sanctum')->group(function (): void {
        Route::get('/my/team', [TeamController::class, 'index']);
        Route::post('/my/team/invite', [TeamController::class, 'invite'])->middleware('throttle:10,1');
        Route::post('/my/team/invitations/{token}/accept', [TeamController::class, 'accept']);
        Route::delete('/my/team/invitations/{teamInvitation}', [TeamController::class, 'destroy']);
        Route::delete('/my/team/members/{user}', [TeamController::class, 'removeMember']);
    });

    // --- E-SIGNATURE (owner) ---
    Route::middleware('auth:sanctum')->group(function (): void {
        Route::get('/my/lease-contracts/{leaseContract}/signatures', [SignatureController::class, 'index']);
        Route::post('/my/lease-contracts/{leaseContract}/signatures', [SignatureController::class, 'store'])->middleware('throttle:10,1');
    });

    // --- E-SIGNATURE (public — no auth required) ---
    Route::get('/signatures/{token}', [SignatureController::class, 'show']);
    Route::post('/signatures/{token}/send-otp', [SignatureController::class, 'sendSignOtp'])->middleware('throttle:10,1');
    Route::post('/signatures/{token}/sign', [SignatureController::class, 'sign'])->middleware('throttle:10,1');
    Route::post('/signatures/{token}/decline', [SignatureController::class, 'decline'])->middleware('throttle:10,1');

    // ─── BROADCASTING AUTH (Sanctum Bearer token) ──────────────────────────
    // The default /broadcasting/auth route uses the 'web' middleware (session auth).
    // The Next.js PWA sends a Sanctum Bearer token, so we need this API route.
    Route::middleware('auth:sanctum')->post('/broadcasting/auth', fn (Request $request) => Broadcast::auth($request));

    // ─── CHAT ────────────────────────────────────────────────────────────────
    Route::middleware('auth:sanctum')->group(function (): void {
        // Per-route rate limits driven by config('chat.rate_limits') so ops
        // can tune them without a code change. Defaults match the historical
        // hard-coded values: 60/min send, 10/min upload, 30/min typing.
        $sendRpm = (int) config('chat.rate_limits.send_message', 60);
        $uploadRpm = (int) config('chat.rate_limits.upload_attachment', 10);
        $typingRpm = (int) config('chat.rate_limits.set_typing', 30);
        $reactionRpm = (int) config('chat.rate_limits.reaction', 60);
        $e2eePutRpm = (int) config('chat.rate_limits.e2ee_identity_update', 20);

        // Conversations
        Route::prefix('conversations')->group(function () use ($sendRpm, $uploadRpm, $typingRpm): void {
            Route::get('/', [ConversationController::class, 'index']);
            Route::post('/', [ConversationController::class, 'store']);
            Route::get('/unread-count', [ConversationController::class, 'unreadCount']);
            Route::get('/{uuid}', [ConversationController::class, 'show']);
            Route::get('/{uuid}/messages', [ConversationController::class, 'messages']);
            Route::post('/{uuid}/messages', [ConversationController::class, 'sendMessage'])
                ->middleware("throttle:{$sendRpm},1");
            Route::post('/{uuid}/attachments', [ConversationController::class, 'uploadAttachment'])
                ->middleware("throttle:{$uploadRpm},1");
            Route::patch('/{uuid}/read', [ConversationController::class, 'markAsRead']);
            Route::post('/{uuid}/typing', [ConversationController::class, 'setTyping'])
                ->middleware("throttle:{$typingRpm},1");
            Route::patch('/{uuid}/archive', [ConversationController::class, 'archive']);
            Route::patch('/{uuid}/unarchive', [ConversationController::class, 'unarchive']);
        });

        // Chat E2EE identity (RSA public key registration — private key stays on device)
        Route::get('/my/chat-e2ee/public-key', [ChatE2eeIdentityController::class, 'show']);
        Route::put('/my/chat-e2ee/public-key', [ChatE2eeIdentityController::class, 'update'])
            ->middleware("throttle:{$e2eePutRpm},1");

        // Individual message operations
        Route::delete('/messages/{uuid}', [MessageController::class, 'destroy']);
        Route::post('/messages/{uuid}/reactions', [MessageController::class, 'addReaction'])
            ->middleware("throttle:{$reactionRpm},1");
        Route::delete('/messages/{uuid}/reactions', [MessageController::class, 'removeReaction'])
            ->middleware("throttle:{$reactionRpm},1");

        // FCM tokens
        Route::post('/fcm/token', [FcmTokenController::class, 'store']);
        Route::delete('/fcm/token', [FcmTokenController::class, 'destroy']);
    });
});
