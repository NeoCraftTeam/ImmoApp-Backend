<?php

declare(strict_types=1);

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;

use App\Enums\UserRole;
use App\Enums\UserType;
use App\Models\Concerns\HasAdminPermissions;
use App\Models\Concerns\HasMultiFactorAuthentication;
use App\Models\Concerns\HasRolesAndType;
use App\Models\Concerns\InteractsWithFilamentPanels;
use App\Services\Auth\UserAuthMailer;
use App\Services\AvatarGeneratorService;
use App\Support\ChatAvatarUrl;
use Clickbar\Magellan\Data\Geometries\Point;
use Database\Factories\UserFactory;
use Eloquent;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthentication;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthenticationRecovery;
use Filament\Auth\MultiFactor\Email\Contracts\HasEmailAuthentication;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasAvatar;
use Filament\Models\Contracts\HasName;
use Filament\Models\Contracts\HasTenants;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Notifications\DatabaseNotificationCollection;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Laragear\WebAuthn\Contracts\WebAuthnAuthenticatable;
use Laragear\WebAuthn\WebAuthnAuthentication;
use Laragear\WebAuthn\WebAuthnData;
use Laravel\Cashier\Billable;
use Laravel\Sanctum\HasApiTokens;
use Laravel\Sanctum\PersonalAccessToken;
use NotificationChannels\WebPush\HasPushSubscriptions;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Collections\MediaCollection;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * @property string $id
 * @property string $firstname
 * @property string $lastname
 * @property string|null $phone_number
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string $avatar
 * @property UserType|null $type
 * @property UserRole $role
 * @property string $city_id
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Collection<int, Ad> $ads
 * @property-read int|null $ads_count
 * @property-read DatabaseNotificationCollection<int, DatabaseNotification> $notifications
 * @property-read int|null $notifications_count
 * @property-read Collection<int, Payment> $payments
 * @property-read int|null $payments_count
 * @property-read Collection<int, PersonalAccessToken> $tokens
 * @property-read int|null $tokens_count
 *
 * @method \Laravel\Sanctum\PersonalAccessToken|\Laravel\Sanctum\TransientToken|null currentAccessToken()
 *
 * @property-read Collection<int, UnlockedAd> $unlockedAds
 * @property-read int|null $unlocked_ads_count
 * @property array<string>|null $app_authentication_recovery_codes
 * @property string|null $app_authentication_secret
 *
 * @method static UserFactory factory($count = null, $state = [])
 * @method static Builder<static>|User newModelQuery()
 * @method static Builder<static>|User newQuery()
 * @method static Builder<static>|User onlyTrashed()
 * @method static Builder<static>|User query()
 * @method static Builder<static>|User whereAvatar($value)
 * @method static Builder<static>|User whereCityId($value)
 * @method static Builder<static>|User whereCreatedAt($value)
 * @method static Builder<static>|User whereDeletedAt($value)
 * @method static Builder<static>|User whereEmail($value)
 * @method static Builder<static>|User whereEmailVerifiedAt($value)
 * @method static Builder<static>|User whereFirstname($value)
 * @method static Builder<static>|User whereId($value)
 * @method static Builder<static>|User whereLastname($value)
 * @method static Builder<static>|User wherePassword($value)
 * @method static Builder<static>|User wherePhoneNumber($value)
 * @method static Builder<static>|User whereRememberToken($value)
 * @method static Builder<static>|User whereRole($value)
 * @method static Builder<static>|User whereType($value)
 * @method static Builder<static>|User whereUpdatedAt($value)
 * @method static Builder<static>|User withTrashed(bool $withTrashed = true)
 * @method static Builder<static>|User withoutTrashed()
 *
 * @property int $point_balance
 * @property-read \Illuminate\Database\Eloquent\Collection<int, PointTransaction> $pointTransactions
 * @property-read int|null $point_transactions_count
 * @property string|null $last_login_at
 * @property string|null $last_login_ip
 * @property string|null $last_login_country
 * @property string|null $last_login_city
 * @property bool $is_active
 * @property bool $is_super_admin
 * @property list<string>|null $admin_permissions
 * @property Carbon|null $onboarding_completed_at
 * @property Carbon|null $last_home_visit_at
 * @property array<string, mixed>|null $preferences
 * @property Carbon|null $must_change_password_at
 * @property string|null $acquisition_source
 * @property string|null $utm_source
 * @property string|null $utm_medium
 * @property string|null $utm_campaign
 * @property string|null $utm_content
 * @property string|null $utm_term
 * @property string|null $referrer_domain
 * @property string|null $chat_e2ee_public_key_pem
 * @property-read City|null $city
 * @property-read MediaCollection<int, Media> $media
 * @property-read Collection<int, Review> $reviews
 * @property-read Collection<int, SiteVisit> $siteVisits
 * @property string|null $agency_id
 * @property-read int|null $reviews_count
 *
 * @method static Builder<static>|User whereIsActive($value)
 * @method static Builder<static>|User whereLastLoginAt($value)
 * @method static Builder<static>|User whereLastLoginIp($value)
 *
 * @mixin Eloquent
 */
class User extends Authenticatable implements FilamentUser, HasAppAuthentication, HasAppAuthenticationRecovery, HasAvatar, HasEmailAuthentication, HasMedia, HasName, HasTenants, MustVerifyEmail, WebAuthnAuthenticatable
{
    use Billable;

    /** @use HasFactory<UserFactory> */
    use HasAdminPermissions, HasApiTokens, HasFactory, HasPushSubscriptions, HasUuids, \Illuminate\Auth\MustVerifyEmail, LogsActivity, Notifiable, SoftDeletes;

    use HasMultiFactorAuthentication, HasRolesAndType, InteractsWithFilamentPanels;
    use InteractsWithMedia;
    use WebAuthnAuthentication;

    protected $table = 'users';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'firstname',
        'lastname',
        'username',
        'bio',
        'email',
        // `password` MUST stay fillable — `RegistrationService::register()`,
        // `UserController::store()`, `ForcePasswordChange::submit()` and the
        // Filament `UserResource` form all rely on `$user->fill(['password' => …])`
        // / `$user->update(['password' => …])`. Removing it silently dropped the
        // value (Eloquent ignores non-fillable keys in `fill()`) and created
        // accounts with `NULL` passwords — total breakage of new signups.
        // The `'password' => 'hashed'` cast in `casts()` ensures any assigned
        // value is automatically bcrypted by the model on save.
        'password',
        'phone_number',
        'phone_is_whatsapp',
        'type',
        'avatar',
        'city_id',
        'location',
        'agency_id',
        // OAuth fields
        'google_id',
        'facebook_id',
        'apple_id',
        'github_id',
        'clerk_id',
        'oauth_provider',
        'oauth_avatar',
        'onboarding_completed_at',
        'trust_score_consent',
        'last_home_visit_at',
        'preferences',
        'locale',
        'registration_ip',
        'must_change_password_at',
        'is_anonymized',
        // Pending OAuth link confirmation
        'pending_oauth_provider',
        'pending_oauth_id',
        'pending_oauth_avatar',
        'pending_oauth_token',
        'pending_oauth_expires_at',
        'chat_e2ee_public_key_pem',
        'is_super_admin',
        'admin_permissions',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = ['password', 'app_authentication_secret', 'app_authentication_recovery_codes', 'remember_token', 'location', 'created_at', 'updated_at', 'google_id', 'facebook_id', 'apple_id', 'github_id'];

    // =========================================================================
    // Boot
    // =========================================================================

    #[\Override]
    protected static function booted(): void
    {
        static::saving(function (User $user): void {
            $user->validateAgentType();
        });

        static::creating(function (User $user): void {
            if (empty($user->role)) {
                $user->role = UserRole::CUSTOMER;
            }

            if (empty($user->firstname)) {
                $user->firstname = 'Nouveau';
            }

            if (empty($user->lastname)) {
                $user->lastname = 'Utilisateur';
            }

            if (empty($user->avatar)) {
                $user->assignDefaultAvatar();
            }

            if (empty($user->username)) {
                $user->username = $user->generateUniqueUsername();
            }
        });

        // NOTE: Welcome-bonus crediting moved to `UserObserver::created()` for
        // SOLID compliance — the User model no longer knows about PointService.
    }

    // =========================================================================
    // Internal helpers
    // =========================================================================

    /**
     * Ensure agent users have a valid type set (individual or agency).
     *
     * @throws InvalidArgumentException
     */
    private function validateAgentType(): void
    {
        if ($this->role === UserRole::AGENT && !in_array($this->type, [UserType::INDIVIDUAL, UserType::AGENCY])) {
            throw new InvalidArgumentException('Invalid agent type. Must be either "individual" or "agency".');
        }
    }

    // =========================================================================
    // Public helpers / accessors
    // =========================================================================

    /**
     * Generate a URL-safe username that is unique across the users table.
     */
    public function generateUniqueUsername(): string
    {
        $base = Str::slug(trim(($this->firstname ?? '').' '.($this->lastname ?? '')));
        if (empty($base)) {
            $base = 'user';
        }

        $candidate = $base;
        $i = 2;

        while (static::where('username', $candidate)->where('id', '!=', $this->id ?? '')->exists()) {
            $candidate = $base.'-'.$i;
            $i++;
        }

        return $candidate;
    }

    /**
     * Generate a default avatar via {@see AvatarGeneratorService} and update
     * the in-memory `avatar` attribute.
     *
     * Kept for backward compatibility — delegates all file I/O to the service.
     */
    public function assignDefaultAvatar(): void
    {
        app(AvatarGeneratorService::class)->generateAndAssign($this);
    }

    /**
     * Replace the avatar media from an uploaded `avatar` file on the request.
     *
     * No-op when the request carries no avatar file, so the model's default
     * avatar (assigned on creation) is preserved.
     */
    public function syncAvatarFromRequest(Request $request): void
    {
        if (!$request->hasFile('avatar')) {
            return;
        }

        $this->clearMediaCollection('avatars');
        $this->addMediaFromRequest('avatar')
            ->usingName($this->firstname.'_'.$this->lastname.'_avatar')
            ->toMediaCollection('avatars');
    }

    // =========================================================================
    // Computed / virtual attributes
    // =========================================================================

    /**
     * Full display name — returns the agency name for agency-type users,
     * otherwise concatenates first and last name.
     */
    public function getFullnameAttribute(): string
    {
        if ($this->type === UserType::AGENCY && $this->relationLoaded('agency') && $this->agency instanceof Agency) {
            return $this->agency->name;
        }

        return trim(($this->firstname ?? '').' '.($this->lastname ?? ''));
    }

    // =========================================================================
    // Relationships
    // =========================================================================

    /** @return HasMany<Ad, $this> */
    public function ads(): HasMany
    {
        return $this->hasMany(Ad::class);
    }

    /** @return HasOne<EmailPreference, $this> */
    public function emailPreference(): HasOne
    {
        return $this->hasOne(EmailPreference::class);
    }

    /** @return BelongsTo<Agency, $this> */
    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class);
    }

    /** @return HasMany<Payment, $this> */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /** @return HasMany<UnlockedAd, $this> */
    public function unlockedAds(): HasMany
    {
        return $this->hasMany(UnlockedAd::class);
    }

    /** @return HasMany<SiteVisit, $this> */
    public function siteVisits(): HasMany
    {
        return $this->hasMany(SiteVisit::class);
    }

    /** @return HasMany<PointTransaction, $this> */
    public function pointTransactions(): HasMany
    {
        return $this->hasMany(PointTransaction::class);
    }

    /** @return BelongsTo<City, $this> */
    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    /** @return HasMany<Review, $this> */
    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    /** @return HasMany<AdReport, $this> */
    public function adReports(): HasMany
    {
        return $this->hasMany(AdReport::class, 'reporter_id');
    }

    /** @return HasMany<AdInteraction, $this> */
    public function adInteractions(): HasMany
    {
        return $this->hasMany(AdInteraction::class);
    }

    /**
     * Bailleurs que cet utilisateur suit.
     *
     * @return BelongsToMany<User, $this>
     */
    public function following(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_follows', 'follower_id', 'followed_id');
    }

    /**
     * Utilisateurs qui suivent ce bailleur.
     *
     * @return BelongsToMany<User, $this>
     */
    public function followers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_follows', 'followed_id', 'follower_id');
    }

    /** @return HasMany<SearchAlert, $this> */
    public function searchAlerts(): HasMany
    {
        return $this->hasMany(SearchAlert::class);
    }

    /** @return HasMany<Document, $this> */
    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }

    /** @return HasMany<TentativeReservation, $this> */
    public function clientReservations(): HasMany
    {
        return $this->hasMany(TentativeReservation::class, 'client_id');
    }

    /** @return HasMany<LeaseContract, $this> */
    public function leaseContracts(): HasMany
    {
        return $this->hasMany(LeaseContract::class);
    }

    /** @return HasMany<LoginHistory, $this> */
    public function loginHistories(): HasMany
    {
        return $this->hasMany(LoginHistory::class);
    }

    /** @return HasMany<TrustScore, $this> */
    public function trustScores(): HasMany
    {
        return $this->hasMany(TrustScore::class);
    }

    /**
     * Eager-loadable "latest trust score" — avoids the per-row
     * `$owner->trustScores()->latest('computed_at')->first()` N+1 fired
     * by AdResource for every ad in a feed/index/search response.
     *
     * We cannot use `latestOfMany('computed_at')` here: that helper
     * generates a `MAX(id)` secondary aggregate as a tiebreaker, and
     * Postgres has no `max(uuid)` function. Trust-score history is
     * small (a handful of rows per user at most), so a plain `hasOne`
     * with `latest('computed_at')` eager-loads cheaply and Laravel
     * keeps the first row per parent (= the latest score per user).
     *
     * @return HasOne<TrustScore, $this>
     */
    public function latestTrustScore(): HasOne
    {
        return $this->hasOne(TrustScore::class)->latest('computed_at');
    }

    // =========================================================================
    // Media Library
    // =========================================================================

    /**
     * Register Spatie media collections for avatar uploads.
     */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('avatars')
            ->singleFile()
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp', 'image/gif']);
    }

    /**
     * Register image conversions applied when media is added.
     */
    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('webp')
            ->width(150)
            ->height(150)
            ->sharpen(10);
    }

    // =========================================================================
    // Chat & realtime helpers
    // =========================================================================

    /**
     * Avatar URL for chat APIs and realtime payloads (Spatie avatars → avatar column → null).
     */
    public function resolveChatAvatarUrl(): ?string
    {
        $mediaUrl = $this->getFirstMediaUrl('avatars');
        $raw = ($mediaUrl !== '' && $mediaUrl !== '0') ? $mediaUrl : $this->avatar;

        return ChatAvatarUrl::resolve($raw !== '' && $raw !== '0' ? $raw : null);
    }

    /**
     * Canal de diffusion des notifications temps réel (channel `broadcast`
     * des notifications Laravel). Aligné sur l'autorisation existante
     * `Broadcast::channel('user.{userId}')` — le défaut Laravel serait
     * `App.Models.User.{id}`, que ni le web ni le mobile n'écoutent.
     */
    public function receivesBroadcastNotificationsOn(): string
    {
        return "user.{$this->id}";
    }

    // =========================================================================
    // WebAuthn
    // =========================================================================

    /**
     * Return the WebAuthn identity data for this user.
     */
    public function webAuthnData(): WebAuthnData
    {
        return WebAuthnData::make($this->email, trim("{$this->firstname} {$this->lastname}"));
    }

    // =========================================================================
    // Casts & logging
    // =========================================================================

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    #[\Override]
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'type' => UserType::class,
            'is_active' => 'boolean',
            'phone_is_whatsapp' => 'boolean',
            'location' => Point::class,
            'app_authentication_secret' => 'encrypted',
            'app_authentication_recovery_codes' => 'encrypted:array',
            'has_email_authentication' => 'boolean',
            'is_anonymized' => 'boolean',
            'onboarding_completed_at' => 'datetime',
            'trust_score_consent' => 'boolean',
            'last_home_visit_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'preferences' => 'array',
            'must_change_password_at' => 'datetime',
            'is_super_admin' => 'boolean',
            'admin_permissions' => 'array',
        ];
    }

    /**
     * Configure which attributes are recorded by Spatie activity log.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['firstname', 'lastname', 'email', 'phone_number', 'role', 'type', 'is_active', 'avatar', 'agency_id'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(fn (string $eventName): string => "Utilisateur « {$this->firstname} {$this->lastname} » {$eventName}");
    }

    // =========================================================================
    // Notifications / Email
    // =========================================================================

    /**
     * Send the password reset notification using our styled email template.
     */
    #[\Override]
    public function sendPasswordResetNotification(mixed $token): void
    {
        app(UserAuthMailer::class)->sendPasswordReset($this, $token);
    }

    /**
     * Send a 6-digit OTP code for email verification instead of a magic link.
     */
    #[\Override]
    public function sendEmailVerificationNotification(): void
    {
        app(UserAuthMailer::class)->sendEmailVerification($this);
    }

    /**
     * Send a verification link by email for admin users (instead of OTP).
     */
    public function sendAdminVerificationEmail(): void
    {
        app(UserAuthMailer::class)->sendAdminVerification($this);
    }
}
