/**
 * Conversation + Message — `GET /conversations` (list) and
 * `GET /conversations/{uuid}/messages` (thread).
 */
export interface ConversationParticipant {
  id: string;
  username?: string;
  /** Nom complet pré-formaté par le backend (`other_participant.name`). */
  name: string;
  avatar?: string | null;
  last_seen_at?: string | null;
}

export interface Conversation {
  uuid: string;
  status?: string;
  /** Interlocuteur — objet unique fourni par le backend (pas un tableau). */
  other_participant?: ConversationParticipant | null;
  last_message?: {
    uuid?: string;
    sender_id: string;
    /** `null` si message scellé (E2EE) — afficher un fallback neutre. */
    body: string | null;
    created_at?: string;
  } | null;
  last_message_at?: string | null;
  unread_count?: number;
  ad?: {
    id: string;
    slug?: string;
    title: string;
    cover_image?: string | null;
  } | null;
}

export interface MessageReaction {
  emoji: string;
  count: number;
  reacted_by_me?: boolean;
}

export interface MessageAttachment {
  id?: string;
  url: string;
  mime_type: string;
  width?: number;
  height?: number;
  size?: number;
  name?: string;
}

export interface Message {
  uuid: string;
  conversation_uuid: string;
  body: string;
  sender_id: string;
  created_at: string;
  read_at?: string | null;
  delivered_at?: string | null;
  attachments?: MessageAttachment[];
  reactions?: MessageReaction[];
  /** Client-only — local optimistic insert avant retour serveur. */
  is_optimistic?: boolean;
  /** Client-only — la mutation a échoué, on garde le brouillon avec retry. */
  is_failed?: boolean;
}

export interface MessagesResponse {
  data: Message[];
  meta?: {
    next_cursor?: string | null;
  };
}
