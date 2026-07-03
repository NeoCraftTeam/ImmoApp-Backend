/**
 * Owner domain types — analytics, viewings, tenants, leases, boosts,
 * subscriptions, expenses, reviews. Each mirrors the corresponding
 * backend resource, trimmed to the fields the mobile screens read.
 */
import type { Ad } from './ad';

/* ------------------------------------------------------------------ */
/* Dashboard stats — GET /my/stats                                    */
/* ------------------------------------------------------------------ */
export interface OwnerStats {
  active_ads_count: number;
  total_ads_count: number;
  occupancy_rate: number;
  active_boosts_count: number;
  recent_viewings_count: number;
  unread_messages_count: number;
  total_revenue: number;
  this_month_revenue: number;
  recent_ads?: Ad[];
  status_breakdown?: Partial<Record<string, number>>;
}

/* ------------------------------------------------------------------ */
/* Analytics — GET /my/ads/analytics                                  */
/* ------------------------------------------------------------------ */
export interface AnalyticsTotals {
  impressions: number;
  views: number;
  favorites: number;
  shares: number;
  contact_clicks: number;
  phone_clicks: number;
  unlocks: number;
  conversion_rate: number;
  engagement_rate: number;
}

export interface AnalyticsTrendPoint {
  date: string;
  views?: number;
  favorites?: number;
  impressions?: number;
}

export interface OwnerAnalytics {
  period: string;
  totals: AnalyticsTotals;
  trends?: AnalyticsTrendPoint[];
  top_ads?: Ad[];
}

/* ------------------------------------------------------------------ */
/* Viewing reservations — GET /my/viewing-reservations                */
/* ------------------------------------------------------------------ */
export type ViewingStatus =
  | 'pending'
  | 'confirmed'
  | 'cancelled'
  | 'expired'
  | 'completed'
  | 'no_show';

export interface ViewingReservation {
  id: string;
  ad?: Pick<Ad, 'id' | 'title' | 'adresse' | 'slug' | 'images'>;
  /** Prospect — visible complet pour le bailleur (TentativeReservationResource). */
  client?: {
    id?: string;
    firstname?: string;
    lastname?: string;
    name?: string;
    avatar?: string | null;
    phone_number?: string | null;
    email?: string;
  };
  status: ViewingStatus;
  status_label?: string;
  /** Date du créneau (Y-m-d) + heures de début/fin (H:i). */
  slot_date: string;
  slot_starts_at?: string | null;
  slot_ends_at?: string | null;
  client_message?: string | null;
  landlord_notes?: string | null;
  cancelled_by?: string | null;
  cancellation_reason?: string | null;
  expires_at?: string;
  created_at?: string;
}

/* ------------------------------------------------------------------ */
/* Boost packs — GET /boost-packs                                     */
/* ------------------------------------------------------------------ */
export interface BoostPack {
  id: string;
  name: string;
  slug: string;
  description?: string;
  duration_days: number;
  boost_score: number;
  price_credits: number;
  is_popular?: boolean;
}

export interface BoostStatus {
  is_boosted: boolean;
  boost_score: number;
  boost_expires_at?: string | null;
  active_boost?: { id: string; name: string; slug: string } | null;
}

/* ------------------------------------------------------------------ */
/* Tenants — GET /my/tenants                                          */
/* ------------------------------------------------------------------ */
export interface Tenant {
  id: string;
  /** Nom complet — le backend stocke un champ unique `name`. */
  name: string;
  phone?: string | null;
  email?: string | null;
  id_number?: string | null;
  notes?: string | null;
  lease_contracts_count?: number;
  created_at?: string;
}

/* ------------------------------------------------------------------ */
/* Lease contracts — GET /my/lease-contracts                          */
/* ------------------------------------------------------------------ */
export type LeaseStatus =
  | 'draft'
  | 'active'
  | 'expired'
  | 'terminated'
  | 'archived';

export interface LeaseContract {
  id: string;
  ad_id: string;
  contract_number?: string | null;
  unit_reference?: string | null;
  status: LeaseStatus;
  status_label?: string;
  lease_start: string;
  lease_end: string;
  lease_duration_months?: number | null;
  monthly_rent: number;
  deposit_amount?: number | null;
  special_conditions?: string | null;
  /** La ressource API est plate — pas d'objet tenant imbriqué. */
  tenant_name?: string | null;
  tenant_phone?: string | null;
  tenant_email?: string | null;
  tenant_id_number?: string | null;
  terminated_at?: string | null;
  termination_reason?: string | null;
  archived_at?: string | null;
  created_at?: string;
}

/* ------------------------------------------------------------------ */
/* Subscriptions — GET /subscriptions/plans + /current               */
/* ------------------------------------------------------------------ */
export interface SubscriptionPlan {
  id: string;
  name: string;
  slug: string;
  description?: string;
  price_monthly: number;
  price_yearly: number;
  price_monthly_formatted?: string;
  price_yearly_formatted?: string;
  yearly_savings?: number;
  duration_days?: number;
  boost_score?: number;
  max_ads?: number;
  is_unlimited?: boolean;
  features?: string[];
  sort_order?: number;
}

export interface CurrentSubscription {
  has_subscription: boolean;
  subscription?: {
    id: string;
    plan: SubscriptionPlan;
    status: string;
    status_label?: string;
    started_at?: string;
    expires_at?: string;
    days_remaining?: number;
    auto_renew?: boolean;
    boosted_ads_count?: number;
  } | null;
}

/* ------------------------------------------------------------------ */
/* Expenses — GET /my/ads/{ad}/expenses                              */
/* ------------------------------------------------------------------ */
export type ExpenseCategory =
  | 'maintenance'
  | 'utilities'
  | 'tax'
  | 'insurance'
  | 'other';

export interface Expense {
  id: string;
  ad_id: string;
  category: ExpenseCategory;
  amount: number;
  date: string;
  description?: string | null;
  created_at?: string;
}

/* ------------------------------------------------------------------ */
/* Reviews — GET /my/reviews (agrégé) + GET /ads/{ad}/reviews        */
/* ------------------------------------------------------------------ */
export interface Review {
  id: string;
  rating: number;
  comment?: string | null;
  /** Réponse du bailleur — champ API `owner_response`. */
  owner_response?: string | null;
  created_at?: string;
  user?: {
    firstname: string;
    lastname?: string;
    avatar?: string | null;
  };
  ad?: { id: string; title: string };
}

/* ------------------------------------------------------------------ */
/* QR / marketing assets — GET /my/ads/{ad}/qr-code etc.             */
/* ------------------------------------------------------------------ */
export interface QrMeta {
  ad_url?: string;
  profile_url?: string;
  qr_data_uri: string;
}

/* ------------------------------------------------------------------ */
/* Reference data                                                     */
/* ------------------------------------------------------------------ */
export interface CityOption {
  id: string;
  name: string;
}

export interface QuarterOption {
  id: string;
  name: string;
  city_id?: string;
}

export interface AdTypeOption {
  id: string;
  name: string;
  desc?: string | null;
}

export interface PropertyAttribute {
  key?: string;
  slug?: string;
  label: string;
  icon?: string | null;
}
