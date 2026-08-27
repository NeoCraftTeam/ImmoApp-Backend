<?php

declare(strict_types=1);

namespace App\Services\Rental;

use App\Enums\LeaseStatus;
use App\Models\Ad;
use App\Models\LeaseContract;
use App\Models\Tenant;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

final class LeaseContractService
{
    /**
     * Re-render the contract PDF using the contract's own current data plus
     * the linked ad. Replaces `pdf_path` (old file is removed afterwards) and
     * stores a fresh ISO-stamped filename so cached signed URLs invalidate.
     */
    public function regeneratePdf(LeaseContract $contract): LeaseContract
    {
        $ad = $contract->ad?->load(['ad_type', 'quarter.city']);
        if (!$ad) {
            return $contract;
        }

        $landlord = $contract->user;

        $data = [
            'landlord_name' => $landlord !== null
                ? trim($landlord->firstname.' '.$landlord->lastname)
                : '',
            'landlord_phone' => $landlord !== null ? (string) ($landlord->phone_number ?? '') : '',
            'landlord_email' => $landlord !== null ? $landlord->email : '',
            'tenant_name' => $contract->tenant_name,
            'tenant_phone' => $contract->tenant_phone,
            'tenant_email' => $contract->tenant_email ?? '',
            'tenant_id_number' => $contract->tenant_id_number ?? '',
            'unit_reference' => $contract->unit_reference,
            'property_title' => $ad->title,
            'property_address' => $ad->adresse,
            'property_type' => $ad->ad_type->name ?? 'Non spécifié',
            'quarter' => $ad->quarter->name ?? '',
            'city' => $ad->quarter->city->name ?? '',
            'bedrooms' => $ad->bedrooms,
            'bathrooms' => $ad->bathrooms,
            'surface_area' => $ad->surface_area,
            'monthly_rent' => (float) $contract->monthly_rent,
            'deposit_amount' => (float) ($contract->deposit_amount ?? 0),
            'lease_start' => Carbon::parse($contract->lease_start)->format('d/m/Y'),
            'lease_end' => $contract->lease_end
                ? Carbon::parse($contract->lease_end)->format('d/m/Y')
                : '',
            'lease_duration_months' => $contract->lease_duration_months,
            'special_conditions' => $contract->special_conditions ?? '',
            'charges_eau' => $ad->charges_eau,
            'charges_electricite' => $ad->charges_electricite,
            'charges_forfaitaires' => $ad->charges_forfaitaires,
            'charges_montant_forfait' => $ad->charges_montant_forfait,
            'generated_at' => now()->format('d/m/Y à H:i'),
            'contract_number' => $contract->contract_number,
        ];

        $pdf = Pdf::loadView('pdf.lease-contract', $data)->setPaper('a4');
        $disk = config('filesystems.app_media_disk');
        $filename = 'lease-contracts/'.str($ad->title)->slug()
            .'-'.now()->format('Ymd-His').'.pdf';
        Storage::disk($disk)->put($filename, $pdf->output());

        $oldPath = $contract->pdf_path;
        $contract->forceFill(['pdf_path' => $filename])->save();

        if ($oldPath && $oldPath !== $filename && Storage::disk($disk)->exists($oldPath)) {
            try {
                Storage::disk($disk)->delete($oldPath);
            } catch (\Throwable) {
                // Best-effort cleanup — never block on storage GC errors.
            }
        }

        return $contract;
    }

    /**
     * Generate a lease contract PDF, store it on disk, and persist a record.
     *
     * @param  array{
     *     tenant_id?: string,
     *     unit_reference?: string,
     *     tenant_name: string,
     *     tenant_phone: string,
     *     tenant_email?: string,
     *     tenant_id_number?: string,
     *     lease_start: string,
     *     lease_duration_months: int,
     *     monthly_rent?: float,
     *     deposit_amount?: float,
     *     special_conditions?: string,
     * }  $tenantData
     */
    public function generate(Ad $ad, User $landlord, array $tenantData): LeaseContract
    {
        $tenant = $this->resolveTenant($landlord, $tenantData);

        $leaseStart = Carbon::parse($tenantData['lease_start']);
        $leaseEnd = $leaseStart->copy()->addMonths($tenantData['lease_duration_months']);
        $contractNumber = 'KH-'.strtoupper(substr($ad->id, 0, 8)).'-'.now()->format('Ymd');
        $monthlyRent = $tenantData['monthly_rent'] ?? (float) $ad->price;
        $depositAmount = $tenantData['deposit_amount'] ?? (float) ($ad->deposit_amount ?? $ad->price);

        $unitReference = $tenantData['unit_reference'] ?? null;

        $data = [
            'landlord_name' => trim("{$landlord->firstname} {$landlord->lastname}"),
            'landlord_phone' => (string) ($landlord->phone_number ?? ''),
            'landlord_email' => $landlord->email,
            'tenant_name' => $tenantData['tenant_name'],
            'tenant_phone' => $tenantData['tenant_phone'],
            'tenant_email' => $tenantData['tenant_email'] ?? '',
            'tenant_id_number' => $tenantData['tenant_id_number'] ?? '',
            'unit_reference' => $unitReference,
            'property_title' => $ad->title,
            'property_address' => $ad->adresse,
            'property_type' => $ad->ad_type->name ?? 'Non spécifié',
            'quarter' => $ad->quarter->name ?? '',
            'city' => $ad->quarter->city->name ?? '',
            'bedrooms' => $ad->bedrooms,
            'bathrooms' => $ad->bathrooms,
            'surface_area' => $ad->surface_area,
            'monthly_rent' => $monthlyRent,
            'deposit_amount' => $depositAmount,
            'lease_start' => $leaseStart->format('d/m/Y'),
            'lease_end' => $leaseEnd->format('d/m/Y'),
            'lease_duration_months' => $tenantData['lease_duration_months'],
            'special_conditions' => $tenantData['special_conditions'] ?? '',
            'charges_eau' => $ad->charges_eau,
            'charges_electricite' => $ad->charges_electricite,
            'charges_forfaitaires' => $ad->charges_forfaitaires,
            'charges_montant_forfait' => $ad->charges_montant_forfait,
            'generated_at' => now()->format('d/m/Y à H:i'),
            'contract_number' => $contractNumber,
        ];

        $pdf = Pdf::loadView('pdf.lease-contract', $data)->setPaper('a4');

        $filename = 'lease-contracts/'.str($ad->title)->slug().'-'.now()->format('Ymd-His').'.pdf';
        Storage::disk(config('filesystems.app_media_disk'))->put($filename, $pdf->output());

        return LeaseContract::create([
            'user_id' => $landlord->id,
            'ad_id' => $ad->id,
            'tenant_id' => $tenant?->id,
            'unit_reference' => $unitReference,
            'contract_number' => $contractNumber,
            'tenant_name' => $tenantData['tenant_name'],
            'tenant_phone' => $tenantData['tenant_phone'],
            'tenant_email' => $tenantData['tenant_email'] ?? null,
            'tenant_id_number' => $tenantData['tenant_id_number'] ?? null,
            'lease_start' => $leaseStart,
            'lease_end' => $leaseEnd,
            'lease_duration_months' => $tenantData['lease_duration_months'],
            'monthly_rent' => $monthlyRent,
            'deposit_amount' => $depositAmount,
            'special_conditions' => $tenantData['special_conditions'] ?? null,
            'pdf_path' => $filename,
            // Mirror the column default ('active') on the in-memory instance:
            // create() does not hydrate DB defaults, so leaving this unset
            // returns a null status that breaks resource serialization and
            // keeps the fresh lease out of the active-lease KPIs.
            'status' => LeaseStatus::Active->value,
        ]);
    }

    /**
     * Resolve the tenant a generated lease should be linked to so the owner's
     * "mes locataires" registry stays populated.
     *
     * An explicit {@see $tenantData['tenant_id']} (already validated as owned
     * by the landlord in {@see GenerateLeaseContractRequest}) wins. Otherwise
     * the tenant is registered from the contract's free-text fields, matched
     * on phone within the landlord's own scope so re-generating a contract for
     * the same person reuses the existing record instead of duplicating it.
     *
     * @param  array{
     *     tenant_id?: string,
     *     tenant_name?: string,
     *     tenant_phone?: string,
     *     tenant_email?: string,
     *     tenant_id_number?: string,
     * }  $tenantData
     */
    private function resolveTenant(User $landlord, array $tenantData): ?Tenant
    {
        $tenantId = $tenantData['tenant_id'] ?? null;
        if ($tenantId !== null && $tenantId !== '') {
            return Tenant::query()
                ->where('user_id', $landlord->id)
                ->find($tenantId);
        }

        $phone = trim((string) ($tenantData['tenant_phone'] ?? ''));
        if ($phone === '') {
            return null;
        }

        return Tenant::query()->firstOrCreate(
            ['user_id' => $landlord->id, 'phone' => $phone],
            [
                'name' => $tenantData['tenant_name'] ?? '',
                'email' => $tenantData['tenant_email'] ?? null,
                'id_number' => $tenantData['tenant_id_number'] ?? null,
            ],
        );
    }
}
