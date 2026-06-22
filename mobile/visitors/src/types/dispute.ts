/**
 * Dispute — `GET /disputes` (list) + `GET /disputes/{id}` (detail).
 */
export type DisputeStatus =
  | 'open'
  | 'review'
  | 'mediation'
  | 'resolved'
  | 'closed'
  | string;

export type EvidenceType =
  | 'photo'
  | 'document'
  | 'screenshot'
  | 'contract'
  | 'receipt'
  | 'other';

export interface DisputeEvidence {
  id: string;
  url: string;
  type: EvidenceType;
  uploaded_at: string;
}

export interface DisputeMessage {
  id: string;
  body: string;
  sender_id: string;
  created_at: string;
}

export interface Dispute {
  id: string;
  reference?: string;
  subject: string;
  description?: string;
  status: DisputeStatus;
  payment_id?: string;
  ad_id?: string;
  amount?: number;
  currency?: string;
  created_at: string;
  updated_at?: string;
  evidences?: DisputeEvidence[];
  messages?: DisputeMessage[];
}

export interface DisputeListResponse {
  data: Dispute[];
  meta?: { current_page?: number; last_page?: number };
}
