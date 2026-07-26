/**
 * Disponibilités de visite — modèle « planning » (schedule) du backend
 * (`AvailabilitySlotResource` / `StoreAvailabilityRequest`). Un planning
 * porte une ou plusieurs plages horaires (`periods`) et une récurrence.
 */
export type Recurrence = 'once' | 'daily' | 'weekly' | 'biweekly' | 'monthly';

export type WeekdaySlug =
  | 'monday'
  | 'tuesday'
  | 'wednesday'
  | 'thursday'
  | 'friday'
  | 'saturday'
  | 'sunday';

export interface AvailabilityPeriod {
  id?: string;
  /** Heure de début au format `HH:mm`. */
  starts_at: string;
  /** Heure de fin au format `HH:mm`. */
  ends_at: string;
}

export interface AvailabilitySlot {
  id: string;
  name: string;
  type?: string;
  is_recurring?: boolean;
  frequency?: string | null;
  /** Config de récurrence renvoyée par la Resource (jours, etc.). */
  frequency_config?: { days?: string[] } | null;
  starts_on: string;
  ends_on?: string | null;
  is_active?: boolean;
  slot_duration?: number;
  buffer_minutes?: number;
  periods?: AvailabilityPeriod[];
}

export interface AvailabilityPayload {
  name: string;
  starts_on: string;
  ends_on?: string | null;
  periods: AvailabilityPeriod[];
  recurrence?: Recurrence;
  recurrence_days?: WeekdaySlug[];
  days_of_month?: number[];
  slot_duration?: number;
  buffer_minutes?: number;
}
