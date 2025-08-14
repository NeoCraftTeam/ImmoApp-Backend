<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

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
        'avatar'
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
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
            throw new \InvalidArgumentException('Invalid agent type. Must be either "individual" or "agency".');
        }
    }

    public function canPublishAds(): bool
    {
        return in_array($this->role, ['customer', 'agent']);
    }

    public function ads(): HasMany
    {
        return $this->hasMany(Ad::class);
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
        ];
    }
}
