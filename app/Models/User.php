<?php

declare(strict_types=1);

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;

use App\Enums\UserRole;
use App\Enums\UserType;
use App\Mail\ForgotPasswordMail;
use App\Mail\VerificationCodeMail;
use App\Mail\VerifyEmailMail;
use App\Models\Concerns\HasAdminPermissions;
use App\Services\Auth\OtpService;
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
use Filament\Panel;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Notifications\DatabaseNotificationCollection;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
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
    protected $hidden = ['password', 'app_authentication_secret', 'app_authentication_recovery_codes', 'remember_token', 'location', 'created_at', 'updated_at', 'google_id', 'facebook_id', 'apple_id'];

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
     * Returns true if the user may publish ads (agent or admin).
     */
    public function canPublishAds(): bool
    {
        return in_array($this->role, [UserRole::AGENT, UserRole::ADMIN]);
    }

    /**
     * Returns true if the user holds the Agent role.
     */
    public function isAgent(): bool
    {
        return $this->role === UserRole::AGENT;
    }

    /**
     * Integrated Next.js owner panel (/owner/*) — AGENT only; admins use Filament.
     */
    public function mayAccessOwnerPanel(): bool
    {
        return $this->isAgent();
    }

    /**
     * Sanctum token name prefix for API session isolation (owner vs client).
     */
    public function sanctumSessionPrefix(): string
    {
        return $this->isAgent() ? 'owner' : 'client';
    }

    /**
     * Returns true if the user holds the Admin role.
     */
    public function isAdmin(): bool
    {
        return $this->role === UserRole::ADMIN;
    }

    /**
     * Returns true if the user holds the Customer role.
     */
    public function isCustomer(): bool
    {
        return $this->role === UserRole::CUSTOMER;
    }

    /**
     * Returns true if the user's type is Individual.
     */
    public function isAnIndividual(): bool
    {
        return $this->type === UserType::INDIVIDUAL;
    }

    /**
     * Returns true if the user's type is Agency.
     */
    public function isAnAgency(): bool
    {
        return $this->type === UserType::AGENCY;
    }

    /**
     * Returns true when the user must change their password on next login.
     */
    public function hasMustChangePassword(): bool
    {
        return $this->must_change_password_at !== null;
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
    // Filament contracts
    // =========================================================================

    /**
     * Determine whether this user may access the given Filament panel.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        if ($this->isAdmin()) {
            return true;
        }

        $panelId = $panel->getId();

        if ($panelId === 'agency') {
            return $this->role === UserRole::AGENT && $this->type === UserType::AGENCY;
        }

        if ($panelId === 'bailleur') {
            return $this->role === UserRole::AGENT && $this->type === UserType::INDIVIDUAL;
        }

        return false;
    }

    /**
     * Return the tenants (agencies) accessible to this user for the given panel.
     *
     * @return Collection<int, Agency>
     */
    public function getTenants(Panel $panel): Collection
    {
        if ($this->isAdmin()) {
            return Agency::all();
        }

        return collect([$this->agency])->filter();
    }

    /**
     * Determine whether this user can access a specific tenant.
     */
    public function canAccessTenant(Model $tenant): bool
    {
        if ($this->isAdmin()) {
            return true;
        }

        return $this->agency_id === $tenant->getKey();
    }

    /**
     * Return the user's display name for Filament UI.
     */
    public function getFilamentName(): string
    {
        return "{$this->firstname} {$this->lastname}";
    }

    /**
     * Return a publicly accessible avatar URL for the Filament UI.
     *
     * Returns null (letting Filament render its placeholder) when no avatar
     * is stored or the file no longer exists on disk.
     */
    public function getFilamentAvatarUrl(): ?string
    {
        if (str_starts_with($this->avatar ?? '', 'http')) {
            return $this->avatar;
        }

        $disk = config('filesystems.app_media_disk');

        if ($this->avatar && Storage::disk($disk)->exists($this->avatar)) {
            return Storage::disk($disk)->url($this->avatar);
        }

        // Privacy: Return null to let Filament/Frontend handle the default placeholder.
        return null;
    }

    /**
     * Avatar URL for chat APIs and realtime payloads (Spatie avatars → avatar column → null).
     */
    public function resolveChatAvatarUrl(): ?string
    {
        $mediaUrl = $this->getFirstMediaUrl('avatars');
        $raw = ($mediaUrl !== '' && $mediaUrl !== '0') ? $mediaUrl : $this->avatar;

        return ChatAvatarUrl::resolve($raw !== '' && $raw !== '0' ? $raw : null);
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
    // Multi-factor: TOTP App
    // =========================================================================

    /** {@inheritDoc} */
    public function getAppAuthenticationSecret(): ?string
    {
        return $this->app_authentication_secret;
    }

    /** {@inheritDoc} */
    public function saveAppAuthenticationSecret(?string $secret): void
    {
        $this->app_authentication_secret = $secret;
        $this->save();
    }

    /**
     * Return the account label shown inside the user's authenticator app.
     *
     * Using the email address ensures uniqueness across multiple accounts.
     */
    public function getAppAuthenticationHolderName(): string
    {
        return $this->email;
    }

    /**
     * {@inheritDoc}
     *
     * @return array<string>|null
     */
    public function getAppAuthenticationRecoveryCodes(): ?array
    {
        return $this->app_authentication_recovery_codes;
    }

    /**
     * {@inheritDoc}
     *
     * @param  array<string>|null  $codes
     */
    public function saveAppAuthenticationRecoveryCodes(?array $codes): void
    {
        $this->app_authentication_recovery_codes = $codes;
        $this->save();
    }

    // =========================================================================
    // Multi-factor: Email OTP
    // =========================================================================

    /** {@inheritDoc} */
    public function hasEmailAuthentication(): bool
    {
        return (bool) $this->has_email_authentication;
    }

    /** {@inheritDoc} */
    public function toggleEmailAuthentication(bool $condition): void
    {
        $this->has_email_authentication = $condition;
        $this->save();
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
        $resetUrl = config('app.frontend_url').'/reset-password?token='.urlencode((string) $token).'&email='.urlencode($this->email);

        $requestedFrom = request()->ip() ?? 'inconnu';
        $requestedAt = now()->translatedFormat('d F Y à H:i');

        Mail::to($this->email, $this->firstname)
            ->queue(new ForgotPasswordMail($resetUrl, $requestedFrom, $requestedAt, $this->role->value));
    }

    /**
     * Send a 6-digit OTP code for email verification instead of a magic link.
     *
     * OTP generation and cache management is delegated to {@see OtpService}.
     * A per-user cooldown prevents flooding when the method is called repeatedly.
     */
    #[\Override]
    public function sendEmailVerificationNotification(): void
    {
        $otpService = app(OtpService::class);

        if ($otpService->isCoolingDown((string) $this->id)) {
            return;
        }

        $otp = $otpService->generate((string) $this->id);

        $requestedFrom = request()->ip() ?? 'inconnu';
        $requestedAt = now()->translatedFormat('d F Y à H:i');

        Mail::to($this->email, $this->firstname)
            ->queue(new VerificationCodeMail($otp, $requestedFrom, $requestedAt, $this->role->value));
    }

    /**
     * Send a verification link by email for admin users (instead of OTP).
     */
    public function sendAdminVerificationEmail(): void
    {
        $ttlMinutes = (int) config('auth.verification.expire', 60);

        URL::forceRootUrl(config('app.url'));

        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes($ttlMinutes),
            ['id' => $this->getKey(), 'hash' => sha1((string) $this->getEmailForVerification())],
        );

        URL::forceRootUrl(config('app.url'));

        $requestedFrom = request()->ip() ?? 'inconnu';
        $requestedAt = now()->translatedFormat('d F Y à H:i');

        Mail::to($this->email, $this->firstname)
            ->queue(new VerifyEmailMail($verificationUrl, $ttlMinutes, $requestedFrom, $requestedAt));
    }
}
