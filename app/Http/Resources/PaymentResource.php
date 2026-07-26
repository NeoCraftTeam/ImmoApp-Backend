<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Support\PaymentPresentation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Payment */
final class PaymentResource extends JsonResource
{
    #[\Override]
    public function toArray(Request $request): array
    {
        $presentation = PaymentPresentation::forPayment($this->resource);

        return [
            'id' => $this->id,
            'reference' => $this->transaction_id,
            'status' => $this->status->value,
            'status_label' => self::statusLabel($this->status),
            'type' => $this->type->value,
            'amount' => $this->amount,
            'gateway' => $this->gateway,
            'gateway_label' => $presentation['gateway_label'],
            'payment_method' => $this->payment_method?->value,
            'payment_method_label' => $presentation['payment_method_label'],
            'payment_method_detail' => $presentation['payment_method_detail'],
            'phone_number' => $this->phone_number,
            'payment_link' => $this->payment_link,
            'ad' => $this->ad_id ? ['id' => $this->ad_id] : null,
            'agency_id' => $this->agency_id,
            'pack_name' => $this->whenLoaded('pointPackage', fn () => $this->pointPackage?->name),
            'points_awarded' => $this->whenLoaded('pointPackage', fn () => $this->pointPackage?->points_awarded),
            'created_at' => $this->created_at,
        ];
    }

    private static function statusLabel(PaymentStatus $status): string
    {
        return match ($status) {
            PaymentStatus::SUCCESS => 'Payé',
            PaymentStatus::FAILED => 'Échoué',
            PaymentStatus::CANCELLED => 'Annulé',
            PaymentStatus::REFUNDED => 'Remboursé',
            PaymentStatus::PENDING => 'En attente',
        };
    }
}
