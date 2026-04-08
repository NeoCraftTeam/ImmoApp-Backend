<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\TrustScore;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin TrustScore
 */
final class TrustScoreResource extends JsonResource
{
    /** @return array<string, mixed> */
    #[\Override]
    public function toArray(Request $request): array
    {
        $tier = $this->tier;

        return [
            'score' => $this->score,
            'tier' => $tier->value,
            'tier_label' => $tier->label(),
            'tier_color' => $tier->hexColor(),
            'role_context' => $this->role_context,
            'breakdown' => $this->components,
            'computed_at' => $this->computed_at->toIso8601String(),
            'tips' => $this->generateTips(),
        ];
    }

    /** @return list<string> */
    private function generateTips(): array
    {
        $tips = [];
        $components = $this->components ?? [];

        foreach ($components as $component) {
            if (isset($component['score'], $component['max']) && $component['score'] < $component['max'] * 0.5) {
                $tips[] = $component['tip'] ?? '';
            }
        }

        return array_values(array_filter($tips));
    }
}
