/**
 * Viewing reservation — `GET /my/reservations`.
 */
export type ReservationStatus =
  | 'pending'
  | 'confirmed'
  | 'cancelled'
  | 'expired'
  | 'completed'
  | 'no_show'
  | string;

export interface Reservation {
  id: string;
  status: ReservationStatus;
  status_label?: string;
  /** Jour du créneau `YYYY-MM-DD` (TentativeReservationResource). */
  slot_date: string;
  /** Heure de début/fin `HH:MM`. */
  slot_starts_at: string;
  slot_ends_at?: string | null;
  client_message?: string | null;
  cancellation_reason?: string | null;
  expires_at?: string | null;
  ad?: {
    id: string;
    slug?: string;
    title: string;
    cover_url?: string | null;
    quarter?: { name?: string; city_name?: string } | null;
  } | null;
  landlord?: {
    id: string;
    firstname: string;
    avatar?: string | null;
  } | null;
  created_at: string;
}
