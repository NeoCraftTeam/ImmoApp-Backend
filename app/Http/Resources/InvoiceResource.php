<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Invoice;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Invoice
 */
class InvoiceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    #[\Override]
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'invoice_number' => $this->invoice_number,
            'plan_name' => $this->plan_name,
            'billing_period' => $this->billing_period,
            'billing_period_label' => $this->billing_period === 'yearly' ? 'Annuel' : 'Mensuel',
            'amount' => $this->amount,
            'amount_formatted' => $this->formatted_amount,
            'currency' => $this->currency,
            'status' => 'paid',
            'status_label' => 'Payé',
            'issued_at' => $this->issued_at->toIso8601String(),
            'issued_at_formatted' => $this->issued_at->format('d/m/Y'),
            'period_start' => $this->period_start?->toIso8601String(),
            'period_end' => $this->period_end?->toIso8601String(),
            'period_label' => $this->period_start && $this->period_end
                ? $this->period_start->format('d/m/Y').' — '.$this->period_end->format('d/m/Y')
                : null,
            'agency' => $this->whenLoaded('agency', function () {
                /** @var \App\Models\Agency $agency */
                $agency = $this->agency;

                return [
                    'id' => $agency->id,
                    'name' => $agency->name,
                ];
            }),
            'download_url' => route('invoices.download', $this->id),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
