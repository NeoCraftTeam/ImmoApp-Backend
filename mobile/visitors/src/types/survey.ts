/**
 * Survey — `GET /public/surveys` (liste) + `GET /public/surveys/{slug}`
 * (détail). Forme alignée sur le backend : question `{ text, type,
 * options }` (pas `prompt`/`kind`). Types de question backend :
 * multiple_choice (choix unique), checkbox (choix multiple), rating,
 * text (texte libre).
 */
export type SurveyQuestionType = 'multiple_choice' | 'checkbox' | 'rating' | 'text';

export interface SurveyQuestion {
  id: string;
  text: string;
  type: SurveyQuestionType;
  /** Liste d'options (array JSON) — chaînes ou { label, value }. */
  options?: (string | { label?: string; value?: string })[] | null;
  order?: number;
}

export interface Survey {
  id: string;
  slug: string;
  title: string;
  description?: string | null;
  is_active?: boolean;
  is_public?: boolean;
  already_submitted?: boolean;
  questions: SurveyQuestion[];
}

export interface SurveyListResponse {
  data: Survey[];
}

/** Réponse envoyée : `answer` est une chaîne (ou un tableau pour checkbox). */
export interface SurveyAnswer {
  question_id: string;
  answer: string | string[];
}
