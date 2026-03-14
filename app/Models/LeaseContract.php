<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeaseContract extends Model
{
    use HasUuids;

    protected $table = 'lease_contracts';

    protected $fillable = [
        'user_id',
        'ad_id',
        'unit_reference',
        'contract_number',
        'tenant_name',
        'tenant_phone',
        'tenant_email',
        'tenant_id_number',
        'lease_start',
        'lease_end',
        'lease_duration_months',
        'monthly_rent',
        'deposit_amount',
        'special_conditions',
        'pdf_path',
    ];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Ad, $this> */
    public function ad(): BelongsTo
    {
        return $this->belongsTo(Ad::class);
    }

    #[\Override]
    protected function casts(): array
    {
        return [
            'lease_start' => 'date',
            'lease_end' => 'date',
            'monthly_rent' => 'decimal:2',
            'deposit_amount' => 'decimal:2',
        ];
    }
}
