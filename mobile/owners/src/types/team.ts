export type TeamMemberRole = 'owner' | 'manager' | 'agent' | 'viewer';

export interface TeamMember {
  id: string;
  firstname: string;
  lastname?: string;
  email: string;
  role: TeamMemberRole;
  status?: 'active' | 'pending';
  avatar?: string | null;
}
