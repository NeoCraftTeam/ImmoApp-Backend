<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ScreeningStatus;
use Database\Factories\TenantScreeningRequestFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property ScreeningStatus $status
 * @property Carbon $expires_at
 * @property Carbon|null $submitted_at
 * @property Carbon|null $reviewed_at
 * @property array<int, string>|null $required_documents
 */
class TenantScreeningRequest extends Model
{
    /** @use HasFactory<TenantScreeningRequestFactory> */
    use HasFactory;

    use HasUuids;

    protected $fillable = [
        'lease_contract_id',
        'requested_by',
        'tenant_name',
        'tenant_email',
        'token',
        'status',
        'required_documents',
        'landlord_notes',
        'review_notes',
        'submitted_at',
        'reviewed_at',
        'reviewed_by',
        'expires_at',
    ];

    #[\Override]
    protected function casts(): array
    {
        return [
            'status' => ScreeningStatus::class,
            'required_documents' => 'array',
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    /**
     * Shareable, token-gated URL the tenant uses to upload their documents.
     *
     * The token is upload-only (the public endpoints never return the
     * documents' signed URLs), so surfacing this link to the landlord — or
     * emailing it to the tenant — does not leak the tenant's PII.
     */
    public function publicUrl(): string
    {
        return rtrim((string) config('app.frontend_url'), '/')."/screening/{$this->token}";
    }

    /** @return BelongsTo<LeaseContract, $this> */
    public function leaseContract(): BelongsTo
    {
        return $this->belongsTo(LeaseContract::class);
    }

    /** @return BelongsTo<User, $this> */
    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    /** @return BelongsTo<User, $this> */
    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /** @return HasMany<TenantScreeningDocument, $this> */
    public function documents(): HasMany
    {
        return $this->hasMany(TenantScreeningDocument::class, 'screening_request_id');
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', ScreeningStatus::Pending->value);
    }
}
