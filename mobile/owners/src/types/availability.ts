export type DayOfWeek = 0 | 1 | 2 | 3 | 4 | 5 | 6;

export interface AvailabilitySlot {
  id: string;
  ad_id: string;
  day_of_week: DayOfWeek;
  start_time: string;
  end_time: string;
  slot_minutes?: number;
  is_active?: boolean;
}

export interface AvailabilityPayload {
  day_of_week: DayOfWeek;
  start_time: string;
  end_time: string;
  slot_minutes?: number;
  is_active?: boolean;
}
