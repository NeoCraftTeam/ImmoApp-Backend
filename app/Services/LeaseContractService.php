<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Ad;
use App\Models\LeaseContract;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

class LeaseContractService
{
    /**
     * Generate a lease contract PDF, store it on disk, and persist a record.
     *
     * @param  array{
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
        $leaseStart = Carbon::parse($tenantData['lease_start']);
        $leaseEnd = $leaseStart->copy()->addMonths($tenantData['lease_duration_months']);
        $contractNumber = 'KH-'.strtoupper(substr($ad->id, 0, 8)).'-'.now()->format('Ymd');
        $monthlyRent = $tenantData['monthly_rent'] ?? (float) $ad->price;
        $depositAmount = $tenantData['deposit_amount'] ?? (float) ($ad->deposit_amount ?? $ad->price);

        $unitReference = $tenantData['unit_reference'] ?? null;

        $data = [
            'landlord_name' => trim("{$landlord->firstname} {$landlord->lastname}"),
            'landlord_phone' => $landlord->phone ?? '',
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

        $filename = 'contracts/'.str($ad->title)->slug().'-'.now()->format('Ymd-His').'.pdf';
        Storage::disk('public')->put($filename, $pdf->output());

        return LeaseContract::create([
            'user_id' => $landlord->id,
            'ad_id' => $ad->id,
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
        ]);
    }
}
