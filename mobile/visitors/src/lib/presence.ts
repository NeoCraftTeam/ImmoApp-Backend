/**
 * Présence façon Messenger, dérivée de `last_seen_at` (rafraîchi côté
 * backend par le middleware TouchLastSeen, throttlé à 1/min) :
 *   - < 2 min  → « En ligne » (pastille verte)
 *   - aujourd'hui → « Vu à HH:MM »
 *   - hier        → « Vu hier à HH:MM »
 *   - avant       → « Vu le JJ/MM »
 *
 * Pas de canal de présence WebSocket nécessaire : la fenêtre de 2 min
 * absorbe le throttle du backend, et l'info reste juste même quand le
 * temps réel est indisponible (repli polling).
 */
export function formatPresence(lastSeenAt?: string | null): {
  online: boolean;
  label: string | null;
} {
  if (!lastSeenAt) return { online: false, label: null };
  const seen = new Date(lastSeenAt);
  const timestamp = seen.getTime();
  if (Number.isNaN(timestamp)) return { online: false, label: null };

  const now = new Date();
  const diffMinutes = (now.getTime() - timestamp) / 60_000;
  if (diffMinutes < 2) return { online: true, label: 'En ligne' };

  const time = seen.toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' });
  const sameDay = seen.toDateString() === now.toDateString();
  if (sameDay) return { online: false, label: `Vu à ${time}` };

  const yesterday = new Date(now);
  yesterday.setDate(now.getDate() - 1);
  if (seen.toDateString() === yesterday.toDateString()) {
    return { online: false, label: `Vu hier à ${time}` };
  }

  const date = seen.toLocaleDateString('fr-FR', { day: '2-digit', month: '2-digit' });
  return { online: false, label: `Vu le ${date}` };
}
