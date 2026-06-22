/**
 * Survey — `GET /public/surveys` (list) + `GET /public/surveys/{slug}` (detail).
 */
export type SurveyQuestionKind =
  | 'short_text'
  | 'long_text'
  | 'single_choice'
  | 'multi_choice'
  | 'rating'
  | 'number';

export interface SurveyOption {
  id: string;
  label: string;
}

export interface SurveyQuestion {
  id: string;
  prompt: string;
  description?: string;
  kind: SurveyQuestionKind;
  is_required?: boolean;
  options?: SurveyOption[];
  max?: number;
  min?: number;
}

export interface Survey {
  id: string;
  slug: string;
  title: string;
  description?: string | null;
  questions: SurveyQuestion[];
  allow_anonymous?: boolean;
  is_open?: boolean;
}

export interface SurveyListResponse {
  data: Survey[];
}

export interface SurveyAnswer {
  question_id: string;
  value?: string | number | null;
  option_ids?: string[];
}
