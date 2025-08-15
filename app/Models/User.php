<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;

use App\UserRole;
use App\UserType;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use InvalidArgumentException;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, softDeletes;

    protected $table = 'user';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'firstname',
        'lastname',
        'email',
        'password',
        'phone_number',
        'type',
        'role',
        'avatar',
        'city_id'
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'created_at',
        'updated_at'
    ];

    protected static function booted(): void
    {
        static::saving(function ($user) {
            $user->assignDefaultAvatar();
            $user->validateAgentType();
        });
    }

    private function assignDefaultAvatar(): void
    {
        if (empty($this->avatar)) {
            $name = trim($this->firstname . ' ' . $this->lastname ?: 'User');
            $this->avatar = 'https://ui-avatars.com/api/?name=' . urlencode($name) . '&background=random';
        }
    }

    private function validateAgentType(): void
    {
        if ($this->role === 'agent' && !in_array($this->type, ['individual', 'agency'])) {
            throw new InvalidArgumentException('Invalid agent type. Must be either "individual" or "agency".');
        }
    }

    public function canPublishAds(): bool
    {
        return in_array($this->role, [
            UserRole::AGENT,
            UserRole::CUSTOMER,
        ]);
    }

    public function ads(): HasMany
    {
        return $this->hasMany(Ad::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function unlockedAds(): HasMany
    {
        return $this->hasMany(UnlockedAd::class);
    }

    /**
     * returns true if the user is an admin.
     */
    public function isAdmin(): bool
    {
        return $this->role === UserRole::ADMIN;
    }

    /**
     * returns true if the user is an agent.
     */
    public function isAgent(): bool
    {
        return $this->role === UserRole::AGENT;
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
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'type' => UserType::class,
        ];
    }
}
