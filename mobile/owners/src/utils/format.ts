/**
 * Formatting helpers shared across owner screens. The market is West/
 * Central Africa (FCFA), so amounts use a space thousands-separator and
 * no decimals.
 */
export function formatMoney(value: number | null | undefined): string {
  if (value === null || value === undefined || Number.isNaN(value)) return '—';
  return new Intl.NumberFormat('fr-FR', { maximumFractionDigits: 0 })
    .format(value)
    .replace(/ /g, ' ');
}

export function formatFcfa(value: number | null | undefined): string {
  if (value === null || value === undefined) return '—';
  return `${formatMoney(value)} FCFA`;
}

/** Compact number for stat cards (1 234 → "1.2k"). */
export function formatCompact(value: number | null | undefined): string {
  if (value === null || value === undefined || Number.isNaN(value)) return '0';
  if (value < 1000) return String(value);
  if (value < 1_000_000) return `${(value / 1000).toFixed(value % 1000 === 0 ? 0 : 1)}k`;
  return `${(value / 1_000_000).toFixed(1)}M`;
}

/** Short French date — "12 juin 2026". */
export function formatDate(iso: string | null | undefined): string {
  if (!iso) return '—';
  const d = new Date(iso);
  if (Number.isNaN(d.getTime())) return '—';
  return d.toLocaleDateString('fr-FR', {
    day: 'numeric',
    month: 'long',
    year: 'numeric',
  });
}

/** Date + time — "12 juin, 14:30". */
export function formatDateTime(iso: string | null | undefined): string {
  if (!iso) return '—';
  const d = new Date(iso);
  if (Number.isNaN(d.getTime())) return '—';
  return d.toLocaleDateString('fr-FR', {
    day: 'numeric',
    month: 'long',
    hour: '2-digit',
    minute: '2-digit',
  });
}
