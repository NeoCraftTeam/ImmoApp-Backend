<?php

declare(strict_types=1);

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;

use App\Enums\UserRole;
use App\Enums\UserType;
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
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Laravel\Sanctum\HasApiTokens;
use Laravel\Sanctum\PersonalAccessToken;
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
 * @property-read \Illuminate\Notifications\DatabaseNotificationCollection<int, \Illuminate\Notifications\DatabaseNotification> $notifications
 * @property-read int|null $notifications_count
 * @property-read Collection<int, Payment> $payments
 * @property-read int|null $payments_count
 * @property-read Collection<int, PersonalAccessToken> $tokens
 * @property-read int|null $tokens_count
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
 * @property string|null $last_login_at
 * @property string|null $last_login_ip
 * @property bool $is_active
 * @property-read City|null $city
 * @property-read MediaCollection<int, Media> $media
 * @property-read Collection<int, Review> $reviews
 * @property int|null $agency_id
 * @property-read int|null $reviews_count
 *
 * @method static Builder<static>|User whereIsActive($value)
 * @method static Builder<static>|User whereLastLoginAt($value)
 * @method static Builder<static>|User whereLastLoginIp($value)
 *
 * @mixin Eloquent
 */
class User extends Authenticatable implements FilamentUser, HasAppAuthentication, HasAppAuthenticationRecovery, HasAvatar, HasEmailAuthentication, HasMedia, HasName, HasTenants, MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasUuids, \Illuminate\Auth\MustVerifyEmail, LogsActivity, Notifiable, softDeletes;

    use InteractsWithMedia;

    protected $table = 'users';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = ['firstname', 'lastname', 'email', 'password', 'phone_number', 'type', 'role', 'avatar', 'city_id', 'is_active', 'location', 'agency_id', 'email_verified_at', 'last_login_ip', 'created_at', 'updated_at'];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = ['password', 'app_authentication_secret', 'app_authentication_recovery_codes', 'remember_token', 'location', 'created_at', 'updated_at'];

    #[\Override]
    protected static function booted(): void
    {
        static::saving(function ($user): void {
            $user->validateAgentType();
        });

        static::creating(function ($user): void {
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
        });
    }

    private function validateAgentType(): void
    {
        if ($this->role === UserRole::AGENT && !in_array($this->type, [UserType::INDIVIDUAL, UserType::AGENCY])) {
            throw new InvalidArgumentException('Invalid agent type. Must be either "individual" or "agency".');
        }
    }

    private function assignDefaultAvatar(): void
    {
        $name = trim(($this->firstname ?? '').' '.($this->lastname ?? ''));
        if (empty($name)) {
            $name = 'U';
        }

        $filename = 'avatars/'.$this->id.'.webp';
        $fullPath = Storage::disk('public')->path($filename);

        if (!is_dir(dirname($fullPath))) {
            mkdir(dirname($fullPath), 0755, true);
        }

        \Laravolt\Avatar\Facade::create($name)->save($fullPath, 80);
        $this->avatar = $filename;
    }

    public function canPublishAds(): bool
    {
        return in_array($this->role, [UserRole::AGENT, UserRole::ADMIN]);
    }

    public function ads(): HasMany
    {
        return $this->hasMany(Ad::class);
    }

    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function unlockedAds(): HasMany
    {
        return $this->hasMany(UnlockedAd::class);
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    /**
     * returns true if the user is an agent.
     */
    public function isAgent(): bool
    {
        return $this->role === UserRole::AGENT;
    }

    public function getFullnameAttribute(): string
    {
        if ($this->type === UserType::AGENCY && $this->agency instanceof Agency) {
            return $this->agency->name;
        }

        return trim(($this->firstname ?? '').' '.($this->lastname ?? ''));
    }

    /**
     * returns true if the user is a customer.
     */
    public function isCustomer(): bool
    {
        return $this->role === UserRole::CUSTOMER;
    }

    /**
     * returns true if the user is an individual.
     */
    public function isAnIndividual(): bool
    {
        return $this->type === UserType::INDIVIDUAL;
    }

    /**
     * returns true if the user is an agency.
     */
    public function isAnAgency(): bool
    {
        return $this->type === UserType::AGENCY;
    }

    /**
     * Définir les collections de médias
     */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('avatars')
            ->singleFile()
            ->acceptsMimeTypes(['image/jpeg', 'image/png']);
    }

    /**
     * Définir les conversions d'images (optionnel)
     */
    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('webp')
            ->width(150)
            ->height(150)
            ->sharpen(10);
    }

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

    public function getTenants(Panel $panel): Collection
    {
        if ($this->isAdmin()) {
            return Agency::all();
        }

        return collect([$this->agency])->filter();
    }

    public function canAccessTenant(\Illuminate\Database\Eloquent\Model $tenant): bool
    {
        if ($this->isAdmin()) {
            return true;
        }

        return $this->agency_id === $tenant->getKey();
    }

    /**
     * returns true if the user is an admin.
     */
    public function isAdmin(): bool
    {
        return $this->role === UserRole::ADMIN;
    }

    public function getFilamentName(): string
    {
        return "{$this->firstname} {$this->lastname}";
    }

    public function getFilamentAvatarUrl(): ?string
    {
        if (str_starts_with($this->avatar ?? '', 'http')) {
            return $this->avatar;
        }

        // Si le fichier existe sur le disque public, on donne son URL
        if ($this->avatar && Storage::disk('public')->exists($this->avatar)) {
            return Storage::disk('public')->url($this->avatar);
        }

        // Privacy: Return null to let Filament/Frontend handle the default placeholder
        return null;
    }

    public function getAppAuthenticationSecret(): ?string
    {
        // This method should return the user's saved app authentication secret.

        return $this->app_authentication_secret;
    }

    public function saveAppAuthenticationSecret(?string $secret): void
    {
        // This method should save the user's app authentication secret.

        $this->app_authentication_secret = $secret;
        $this->save();
    }

    public function getAppAuthenticationHolderName(): string
    {
        // In a user's authentication app, each account can be represented by a "holder name".
        // If the user has multiple accounts in your app, it might be a good idea to use
        // their email address as then they are still uniquely identifiable.

        return $this->email;
    }

    /**
     * @return array<string>|null
     */
    public function getAppAuthenticationRecoveryCodes(): ?array
    {
        // This method should return the user's saved app authentication recovery codes.

        return $this->app_authentication_recovery_codes;
    }

    /**
     * @param  array<string> | null  $codes
     */
    public function saveAppAuthenticationRecoveryCodes(?array $codes): void
    {
        // This method should save the user's app authentication recovery codes.

        $this->app_authentication_recovery_codes = $codes;
        $this->save();
    }

    public function hasEmailAuthentication(): bool
    {
        // This method should return true if the user has enabled email authentication.

        return $this->has_email_authentication;
    }

    public function toggleEmailAuthentication(bool $condition): void
    {
        // This method should save whether or not the user has enabled email authentication.

        $this->has_email_authentication = $condition;
        $this->save();
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'string',
            'role' => UserRole::class,
            'type' => UserType::class,
            'is_active' => 'boolean',
            'location' => Point::class,
            'app_authentication_secret' => 'encrypted',
            'app_authentication_recovery_codes' => 'encrypted:array',
            'has_email_authentication' => 'boolean',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['firstname', 'lastname', 'email', 'phone_number', 'role', 'type', 'is_active', 'avatar', 'agency_id'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(fn (string $eventName): string => "Utilisateur « {$this->firstname} {$this->lastname} » {$eventName}");
    }
}
