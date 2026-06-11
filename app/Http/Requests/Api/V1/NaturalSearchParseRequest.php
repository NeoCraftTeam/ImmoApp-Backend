<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates POST /api/v1/ads/search/parse.
 *
 * - `q`: free text, hard-capped at 300 chars to prevent LLM10:2025 abuse.
 * - `display_currency`: optional ISO-4217 code (e.g. EUR, USD) used by the
 *   LLM to interpret non-XAF amounts in the query.
 * - `owner_context`: when true, requires an authenticated landlord
 *   (AGENT or ADMIN) and switches the prompt to the dashboard surface.
 */
final class NaturalSearchParseRequest extends FormRequest
{
    public function authorize(): bool
    {
        if (!$this->boolean('owner_context')) {
            return true;
        }

        $user = $this->user();

        return $user instanceof User
            && in_array($user->role, [UserRole::AGENT, UserRole::ADMIN], true);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'q' => ['required', 'string', 'max:300'],
            'display_currency' => ['nullable', 'string', 'regex:/^[A-Z]{3}$/'],
            'owner_context' => ['nullable', 'boolean'],
        ];
    }

    public function context(): string
    {
        return $this->boolean('owner_context') ? 'owner' : 'customer';
    }

    public function normalisedQuery(): string
    {
        return trim((string) $this->input('q'));
    }

    public function normalisedDisplayCurrency(): ?string
    {
        $value = $this->input('display_currency');

        return $value === null ? null : strtoupper((string) $value);
    }
}
