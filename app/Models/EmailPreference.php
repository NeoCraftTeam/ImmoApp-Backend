<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class EmailPreference extends Model
{
    protected $fillable = [
        'user_id',
        'ad_updates',
        'search_alerts',
        'subscription_updates',
        'survey_notifications',
        'admin_notifications',
        'welcome_emails',
        'engagement_emails',
        'digest_emails',
        'unsubscribe_token',
    ];

    #[\Override]
    protected function casts(): array
    {
        return [
            'ad_updates' => 'boolean',
            'search_alerts' => 'boolean',
            'subscription_updates' => 'boolean',
            'survey_notifications' => 'boolean',
            'admin_notifications' => 'boolean',
            'welcome_emails' => 'boolean',
            'engagement_emails' => 'boolean',
            'digest_emails' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get or create email preferences for a user, generating an unsubscribe token.
     */
    public static function getOrCreateForUser(User $user): self
    {
        return self::firstOrCreate(
            ['user_id' => $user->id],
            ['unsubscribe_token' => Str::random(64)]
        );
    }

    /**
     * Check if a specific email category is enabled.
     */
    public function isEnabled(string $category): bool
    {
        return (bool) ($this->{$category} ?? true);
    }
}
