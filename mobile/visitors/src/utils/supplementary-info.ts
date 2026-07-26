import type { Ad } from '@/types/ad';

/**
 * Same guard as the web `SupplementaryInfoCard`: the section renders
 * only when at least one supplementary field is filled (the API omits
 * them entirely while the ad is locked).
 */
export function hasSupplementaryInfo(ad: Ad): boolean {
  return Boolean(
    ad.deposit_amount ||
      ad.minimum_lease_duration ||
      ad.detailed_charges ||
      ad.property_condition_pdf ||
      ad.charges_eau ||
      ad.charges_electricite ||
      ad.charges_autres ||
      ad.charges_forfaitaires ||
      ad.charges_montant_forfait,
  );
}

/**
 * Web parity: numeric charge amounts render as `50 000 FCFA/mois`;
 * anything non-numeric is shown as-is (free-text DB columns).
 */
export function formatChargeAmount(value: number | string): string {
  const parsed = Number(value);
  if (Number.isFinite(parsed) && String(value).trim() !== '') {
    return `${parsed.toLocaleString('fr-FR')} FCFA/mois`;
  }
  return String(value);
}
