<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Enums\UserRole;
use App\Models\Dispute;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Dispute
 */
class DisputeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    #[\Override]
    public function toArray(Request $request): array
    {
        $viewer = $request->user();
        $isAdmin = $viewer !== null && ($viewer->role ?? null) === UserRole::ADMIN;

        return [
            'id' => $this->id,
            'reference' => $this->reference,
            'type' => $this->type->value,
            'type_label' => $this->type->getLabel(),
            'status' => $this->status->value,
            'status_label' => $this->status->getLabel(),
            'is_open' => $this->status->isOpen(),
            'is_resolved' => $this->status->isResolved(),

            'initiator' => [
                'id' => $this->initiator?->id,
                'name' => $this->initiator?->fullname,
            ],
            'respondent' => [
                'id' => $this->respondent?->id,
                'name' => $this->respondent?->fullname,
            ],
            'admin' => $this->whenLoaded('admin', fn () => $this->admin ? [
                'id' => $this->admin->id,
                'name' => $this->admin->fullname,
            ] : null),

            'ad_id' => $this->ad_id,
            'lease_id' => $this->lease_id,
            'payment_id' => $this->payment_id,

            'title' => $this->title,
            'description' => $this->description,
            'amount_claimed' => $this->amount_claimed,
            'resolution_note' => $this->when(
                $isAdmin || $this->status->isResolved(),
                $this->resolution_note,
            ),

            'sla_deadline' => $this->sla_deadline->toIso8601String(),
            'resolved_at' => $this->resolved_at?->toIso8601String(),
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),

            'messages' => DisputeMessageResource::collection(
                $this->whenLoaded('messages', fn () => $this->messages
                    ->reject(fn ($m) => $m->is_internal && !$isAdmin)
                    ->values())
            ),
            'evidences' => DisputeEvidenceResource::collection($this->whenLoaded('evidences')),

            'allowed_transitions' => $this->when(
                $isAdmin,
                fn () => array_map(fn ($s) => $s->value, $this->status->allowedNext()),
            ),
        ];
    }
}
