/**
 * Property attribute metadata — labels + icon hints for the equipment
 * keys returned on an ad (e.g. "balcon", "ascenseur"). Backend endpoint:
 * `GET /property-attributes` (CDN-cached 30 minutes).
 */
export interface PropertyAttributeMeta {
  key: string;
  label: string;
  /** Lucide icon name; the mobile component maps it to a Tamagui icon. */
  icon?: string;
  category?: string;
}

export interface PropertyAttributesResponse {
  data: PropertyAttributeMeta[];
}
