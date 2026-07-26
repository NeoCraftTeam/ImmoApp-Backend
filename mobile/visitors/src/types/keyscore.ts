/**
 * KeyScore — quality score for an ad, 0-100, with a breakdown by
 * criterion. Backend endpoint: `GET /ads/{ad}/keyscore`.
 */
export type KeyScoreBreakdown = Record<string, {
  score: number;
  weight?: number;
  label?: string;
}>;

export interface KeyScorePayload {
  score: number;
  label?: string;
  breakdown?: KeyScoreBreakdown;
}
