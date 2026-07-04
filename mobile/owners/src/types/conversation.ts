/**
 * Owner-side chat types — mirror du visitor app. Une conversation owner
 * est strictement la même chose côté backend ; on duplique pour garder
 * les workspaces isolés (pas de cross-import entre `visitors/` et
 * `owners/`).
 */

export interface ConversationParticipant {
  id: string;
  username?: string;
  /** Nom complet pré-formaté par le backend (`other_participant.name`). */
  name: string;
  avatar?: string | null;
  last_seen_at?: string | null;
}

export interface ConversationPreview {
  /** Identifiant de conversation renvoyé par le backend (`uuid`). */
  uuid: string;
  status?: string;
  /** Interlocuteur — objet unique fourni par le backend (pas un tableau). */
  other_participant?: ConversationParticipant | null;
  last_message?: {
    uuid?: string;
    body: string | null;
    created_at?: string;
    sender_id?: string;
  } | null;
  last_message_at?: string | null;
  unread_count: number;
  ad?: { id: string; title: string; slug?: string } | null;
}

export interface ConversationMessage {
  uuid: string;
  conversation_uuid: string;
  sender_id: string;
  sender?: {
    id: string;
    name: string;
    avatar?: string | null;
  } | null;
  /** `null` pour un message sans corps (pièce jointe seule) ou scellé E2EE. */
  body: string | null;
  is_client_sealed?: boolean;
  attachments?: { id: string; url: string; type?: string }[];
  /** Déjà groupées par le backend (MessageResource::buildReactions). */
  reactions?: { emoji: string; count: number; user_ids: string[] }[];
  read_at?: string | null;
  created_at: string;
  client_id?: string;
}
