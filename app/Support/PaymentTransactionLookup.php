<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\PaymentType;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Resolves {@see Payment} rows from KeyHome {@code tx_ref} (KH-*) or gateway references (MTX-*, SANDBOX_*).
 */
final class PaymentTransactionLookup
{
    public static function isKeyhomeTxRef(string $value): bool
    {
        return (bool) preg_match('/^KH-[A-Z0-9]{6,32}$/i', $value);
    }

    public static function isGatewayReference(string $value): bool
    {
        if (self::isKeyhomeTxRef($value)) {
            return false;
        }

        return (bool) preg_match('/^(MTX-|SANDBOX_)[A-Z0-9_-]+$/i', $value);
    }

    /**
     * @param  Builder<Payment>  $query
     */
    public static function applyGatewayReferenceFilter(Builder $query, string $gatewayReference): void
    {
        $query->where(function (Builder $inner) use ($gatewayReference): void {
            $inner->where('gateway_response->genius_reference', $gatewayReference)
                ->orWhere('gateway_response->reference', $gatewayReference);
        });
    }

    /**
     * Find a payment owned by the user using either KeyHome tx_ref or a GeniusPay reference.
     */
    public static function findForUser(
        User $user,
        ?string $txRef,
        ?string $gatewayReference,
        ?PaymentType $type = null,
    ): ?Payment {
        $txRef = is_string($txRef) && $txRef !== '' ? $txRef : null;
        $gatewayReference = is_string($gatewayReference) && $gatewayReference !== '' ? $gatewayReference : null;

        if ($txRef === null && $gatewayReference === null) {
            return null;
        }

        $query = Payment::query()->where('user_id', $user->id);

        if ($type instanceof PaymentType) {
            $query->where('type', $type);
        }

        if ($txRef !== null) {
            return $query->where('transaction_id', $txRef)->first();
        }

        self::applyGatewayReferenceFilter($query, $gatewayReference);

        return $query->latest()->first();
    }

    /**
     * Public status lookup — no user scope (capability token is the opaque reference).
     */
    public static function findByPublicReference(string $reference): ?Payment
    {
        if (self::isKeyhomeTxRef($reference)) {
            return Payment::query()
                ->where('transaction_id', $reference)
                ->first();
        }

        if (!self::isGatewayReference($reference)) {
            return null;
        }

        $query = Payment::query();
        self::applyGatewayReferenceFilter($query, $reference);

        return $query->first();
    }
}
