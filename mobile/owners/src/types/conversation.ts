/**
 * Owner-side chat types — mirror du visitor app. Une conversation owner
 * est strictement la même chose côté backend ; on duplique pour garder
 * les workspaces isolés (pas de cross-import entre `visitors/` et
 * `owners/`).
 */

export interface ConversationParticipant {
  id: string;
  firstname: string;
  lastname?: string;
  avatar?: string | null;
}

export interface ConversationPreview {
  id: string;
  uuid?: string;
  other_user: ConversationParticipant;
  last_message?: {
    body: string | null;
    created_at: string;
    sender_id?: string;
  } | null;
  unread_count: number;
  ad?: { id: string; title: string; slug?: string } | null;
  updated_at?: string;
}

export interface ConversationMessage {
  id: string;
  conversation_id: string;
  sender_id: string;
  body: string | null;
  attachments?: { id: string; url: string; type?: string }[];
  reactions?: { id: string; emoji: string; user_id: string }[];
  read_at?: string | null;
  created_at: string;
  client_id?: string;
}
