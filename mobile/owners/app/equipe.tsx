import { useState } from 'react';
import { Mail, Trash2, UserPlus, Users2 } from '@tamagui/lucide-icons';
import { Alert, RefreshControl, ScrollView } from 'react-native';
import {
  Button,
  Input,
  Paragraph,
  Sheet,
  Spinner,
  XStack,
  YStack,
} from 'tamagui';

import { extractApiErrorMessage } from '@/api/client';
import { useSession } from '@/auth/SessionProvider';
import { EmptyState } from '@/components/EmptyState';
import { ScreenHeader } from '@/components/ScreenHeader';
import {
  useInviteTeamMember,
  useRemoveTeamMember,
  useTeam,
} from '@/hooks/useTeam';
import { brand } from '@/theme/tokens';
import type { TeamMemberRole } from '@/types/team';

const ROLE_LABEL: Record<TeamMemberRole, string> = {
  owner: 'Propriétaire',
  manager: 'Gestionnaire',
  agent: 'Agent',
  viewer: 'Lecture seule',
};

export default function TeamScreen() {
  const { isAuthenticated } = useSession();
  const { data: team = [], isLoading, isRefetching, refetch } = useTeam(isAuthenticated);
  const invite = useInviteTeamMember();
  const remove = useRemoveTeamMember();

  const [showInvite, setShowInvite] = useState(false);
  const [email, setEmail] = useState('');
  const [firstname, setFirstname] = useState('');
  const [role, setRole] = useState<TeamMemberRole>('agent');

  const onInvite = () => {
    if (!email.trim()) return;
    invite.mutate(
      { email: email.trim(), firstname: firstname.trim() || undefined, role },
      {
        onSuccess: () => {
          setShowInvite(false);
          setEmail('');
          setFirstname('');
          setRole('agent');
          Alert.alert('Succès', 'Invitation envoyée.');
        },
        onError: (err) => Alert.alert('Erreur', extractApiErrorMessage(err)),
      },
    );
  };

  const onRemove = (id: string, name: string) => {
    Alert.alert('Retirer', `Retirer ${name} de l'équipe ?`, [
      { text: 'Annuler', style: 'cancel' },
      {
        text: 'Retirer',
        style: 'destructive',
        onPress: () => remove.mutate(id),
      },
    ]);
  };

  return (
    <YStack flex={1} backgroundColor="$background">
      <ScreenHeader
        title="Équipe"
        right={
          <Button
            size="$3"
            chromeless
            borderRadius={999}
            backgroundColor="$brand"
            color="white"
            paddingHorizontal={12}
            onPress={() => setShowInvite(true)}
            icon={<UserPlus size={14} color="white" />}
          >
            Inviter
          </Button>
        }
      />

      <ScrollView
        contentContainerStyle={{ padding: 16, paddingBottom: 32, gap: 10 }}
        refreshControl={
          <RefreshControl refreshing={isRefetching} onRefresh={refetch} tintColor={brand.primary} />
        }
      >
        {isLoading ? (
          <YStack height={320} alignItems="center" justifyContent="center">
            <Spinner color={brand.primary} size="large" />
          </YStack>
        ) : team.length === 0 ? (
          <YStack height={320}>
            <EmptyState
              icon={<Users2 size={28} color={brand.primary} />}
              title="Aucun collaborateur"
              hint="Invitez un agent ou un gestionnaire pour partager la gestion de vos annonces."
              ctaLabel="Inviter un collaborateur"
              onPressCta={() => setShowInvite(true)}
            />
          </YStack>
        ) : (
          team.map((m) => {
            const fullName = `${m.firstname} ${m.lastname ?? ''}`.trim();
            return (
              <XStack
                key={m.id}
                padding={14}
                gap={12}
                borderRadius={14}
                borderWidth={1}
                borderColor="$slate300"
                alignItems="center"
              >
                <YStack
                  width={44}
                  height={44}
                  borderRadius={22}
                  alignItems="center"
                  justifyContent="center"
                  backgroundColor={brand.primaryAlpha10}
                >
                  <Paragraph fontSize={15} fontWeight="800" color={brand.primary}>
                    {(m.firstname?.[0] ?? '?').toUpperCase()}
                  </Paragraph>
                </YStack>
                <YStack flex={1} gap={2}>
                  <Paragraph fontSize={14} fontWeight="700" color="$slate900">
                    {fullName || m.email}
                  </Paragraph>
                  <Paragraph fontSize={11.5} color="$slate500">
                    {m.email} • {ROLE_LABEL[m.role] ?? m.role}
                  </Paragraph>
                </YStack>
                {m.role !== 'owner' ? (
                  <Button
                    size="$2"
                    chromeless
                    onPress={() => onRemove(m.id, fullName || m.email)}
                    icon={<Trash2 size={14} color={brand.danger} />}
                  />
                ) : null}
              </XStack>
            );
          })
        )}
      </ScrollView>

      {/* Invite sheet */}
      <Sheet
        modal
        open={showInvite}
        onOpenChange={setShowInvite}
        snapPoints={[55]}
        dismissOnSnapToBottom
      >
        <Sheet.Overlay />
        <Sheet.Frame padding={20} gap={12}>
          <Sheet.Handle />
          <Paragraph fontSize={18} fontWeight="800">
            Inviter un collaborateur
          </Paragraph>
          <YStack gap={6}>
            <Paragraph fontSize={12.5} fontWeight="700" color="$slate700">
              Email
            </Paragraph>
            <Input
              value={email}
              onChangeText={setEmail}
              keyboardType="email-address"
              autoCapitalize="none"
              placeholder="collegue@exemple.com"
            />
          </YStack>
          <YStack gap={6}>
            <Paragraph fontSize={12.5} fontWeight="700" color="$slate700">
              Prénom (optionnel)
            </Paragraph>
            <Input value={firstname} onChangeText={setFirstname} placeholder="Jean" />
          </YStack>
          <YStack gap={6}>
            <Paragraph fontSize={12.5} fontWeight="700" color="$slate700">
              Rôle
            </Paragraph>
            <XStack gap={6} flexWrap="wrap">
              {(['agent', 'manager', 'viewer'] as TeamMemberRole[]).map((r) => {
                const isSel = role === r;
                return (
                  <Button
                    key={r}
                    size="$2"
                    chromeless
                    borderRadius={999}
                    backgroundColor={isSel ? '$brand' : '$slate100'}
                    onPress={() => setRole(r)}
                    paddingHorizontal={12}
                  >
                    <Paragraph fontSize={12} fontWeight="700" color={isSel ? 'white' : '$slate700'}>
                      {ROLE_LABEL[r]}
                    </Paragraph>
                  </Button>
                );
              })}
            </XStack>
          </YStack>

          <Button
            marginTop={8}
            size="$4"
            backgroundColor="$brand"
            color="white"
            fontWeight="800"
            borderRadius={12}
            disabled={!email.trim() || invite.isPending}
            opacity={!email.trim() ? 0.5 : 1}
            onPress={onInvite}
            icon={<Mail size={16} color="white" />}
          >
            {invite.isPending ? 'Envoi…' : 'Envoyer l’invitation'}
          </Button>
        </Sheet.Frame>
      </Sheet>
    </YStack>
  );
}
