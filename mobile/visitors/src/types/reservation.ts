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
  starts_at: string;
  ends_at?: string | null;
  notes?: string | null;
  cancellation_reason?: string | null;
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
