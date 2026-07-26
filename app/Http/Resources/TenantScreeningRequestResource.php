<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\TenantScreeningRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin TenantScreeningRequest */
final class TenantScreeningRequestResource extends JsonResource
{
    /** @return array<string, mixed> */
    #[\Override]
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'lease_contract_id' => $this->lease_contract_id,
            'tenant_name' => $this->tenant_name,
            'tenant_email' => $this->tenant_email,
            // SECURITY: `token` is the tenant's upload secret. It used to
            // be returned to the landlord-facing endpoints, which meant
            // any forwarded email or screenshot of the landlord view
            // leaked a capability that fetched the tenant's PII (ID
            // cards, payslips, bank statements) for 14 days. Landlords
            // do not need the token — they review via authenticated
            // routes — so the field is no longer emitted.
            'status' => $this->status->value,
            'status_label' => $this->status->getLabel(),
            'required_documents' => $this->required_documents,
            'landlord_notes' => $this->landlord_notes,
            'review_notes' => $this->review_notes,
            'submitted_at' => $this->submitted_at?->toIso8601String(),
            'reviewed_at' => $this->reviewed_at?->toIso8601String(),
            'expires_at' => $this->expires_at->toIso8601String(),
            'documents' => TenantScreeningDocumentResource::collection(
                $this->whenLoaded('documents')
            ),
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
