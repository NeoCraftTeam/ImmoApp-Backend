<?php

declare(strict_types=1);

namespace App\Services\Payment;

use App\Enums\PaymentMethod;
use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

/**
 * Admin-controlled payment-method gating.
 *
 * Each `PaymentMethod` case has a runtime on/off switch persisted in the
 * `settings` table under the key `payment_method:{value}:enabled`. The state
 * is cached for 5 minutes (Redis) so the gating cost on a hot endpoint like
 * `GET /api/v1/payments/methods` stays in the microsecond range.
 *
 * Defaults (when no override is set) :
 *   - mobile_money  → enabled (GeniusPay)
 *   - orange_money  → enabled (GeniusPay)
 *   - card          → enabled (Stripe)
 *
 * Toggling a method off through Filament hides it from the public API
 * response and rejects any new payment initiation that targets it.
 */
final class PaymentMethodGateService
{
    /**
     * Cache TTL for runtime overrides (5 minutes — same as `FeatureFlagService`).
     */
    private const int CACHE_TTL = 300;

    /**
     * Settings key prefix.
     */
    private const string SETTING_PREFIX = 'payment_method:';

    /**
     * Returns true when the given method is currently enabled.
     */
    public function isEnabled(PaymentMethod $method): bool
    {
        $override = $this->getRuntimeOverride($method);

        if ($override !== null) {
            return $override;
        }

        return $this->defaultFor($method);
    }

    /**
     * Hard-coded enable-by-default rule per method. Using a `match`
     * statement so that adding a new `PaymentMethod` case forces a
     * compile-time decision instead of silently defaulting to `false`.
     */
    private function defaultFor(PaymentMethod $method): bool
    {
        return match ($method) {
            PaymentMethod::ORANGE_MONEY,
            PaymentMethod::MOBILE_MONEY,
            PaymentMethod::CARD => true,
        };
    }

    /**
     * Enable a method at runtime (persists to the settings table).
     */
    public function enable(PaymentMethod $method): void
    {
        $this->setRuntimeOverride($method, true);
    }

    /**
     * Disable a method at runtime (persists to the settings table).
     */
    public function disable(PaymentMethod $method): void
    {
        $this->setRuntimeOverride($method, false);
    }

    /**
     * Drop the runtime override so the default takes effect again.
     */
    public function reset(PaymentMethod $method): void
    {
        Setting::query()
            ->where('key', self::SETTING_PREFIX.$method->value.':enabled')
            ->delete();

        Cache::forget($this->cacheKey($method));
    }

    /**
     * Returns every enabled `PaymentMethod` case in stable enum order.
     *
     * @return list<PaymentMethod>
     */
    public function available(): array
    {
        return array_values(array_filter(
            PaymentMethod::cases(),
            $this->isEnabled(...),
        ));
    }

    /**
     * Compact descriptor used by `GET /api/v1/payments/methods`.
     *
     * @return list<array{value: string, label: string, gateway: string, enabled: bool}>
     */
    public function describeAvailable(): array
    {
        return array_map(
            fn (PaymentMethod $m): array => [
                'value' => $m->value,
                'label' => $m->label(),
                'gateway' => $m->gateway()->value,
                'enabled' => true,
            ],
            $this->available(),
        );
    }

    /**
     * Returns the full enum view including disabled methods. Intended for
     * Filament admin UIs — never exposed publicly.
     *
     * @return list<array{value: string, label: string, gateway: string, enabled: bool}>
     */
    public function describeAll(): array
    {
        return array_map(
            fn (PaymentMethod $m): array => [
                'value' => $m->value,
                'label' => $m->label(),
                'gateway' => $m->gateway()->value,
                'enabled' => $this->isEnabled($m),
            ],
            PaymentMethod::cases(),
        );
    }

    private function getRuntimeOverride(PaymentMethod $method): ?bool
    {
        $value = Cache::remember(
            $this->cacheKey($method),
            self::CACHE_TTL,
            fn () => Setting::query()
                ->where('key', self::SETTING_PREFIX.$method->value.':enabled')
                ->value('value'),
        );

        if ($value === null) {
            return null;
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    private function setRuntimeOverride(PaymentMethod $method, bool $enabled): void
    {
        Setting::query()->updateOrCreate(
            ['key' => self::SETTING_PREFIX.$method->value.':enabled'],
            ['value' => $enabled ? '1' : '0'],
        );

        Cache::forget($this->cacheKey($method));
    }

    private function cacheKey(PaymentMethod $method): string
    {
        return self::SETTING_PREFIX.$method->value.':enabled';
    }
}
