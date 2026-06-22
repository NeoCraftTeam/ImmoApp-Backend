/**
 * Conversation + Message — `GET /conversations` (list) and
 * `GET /conversations/{uuid}/messages` (thread).
 */
export interface ConversationParticipant {
  id: string;
  firstname: string;
  avatar?: string | null;
}

export interface Conversation {
  uuid: string;
  participants: ConversationParticipant[];
  last_message?: {
    body: string;
    sender_id: string;
    created_at: string;
  } | null;
  unread_count?: number;
  ad?: {
    id: string;
    slug?: string;
    title: string;
    cover_url?: string | null;
  } | null;
  updated_at?: string;
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
