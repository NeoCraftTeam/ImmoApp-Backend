import { ArrowLeft, Github, KeyRound, Link2, ShieldCheck, Trash2 } from '@tamagui/lucide-icons';
import { Stack, useRouter } from 'expo-router';
import { useState } from 'react';
import { Alert, Pressable, ScrollView } from 'react-native';
import { Button, H2, Paragraph, Spinner, XStack, YStack } from 'tamagui';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

import { extractApiErrorMessage } from '@/api/client';
import { DeleteAccountModal } from '@/components/DeleteAccountModal';
import { PasswordInput } from '@/components/PasswordInput';
import { useLinkProvider, useUnlinkProvider } from '@/hooks/useLinkedAccounts';
import { useMe } from '@/hooks/useMe';
import { useChangePassword, useDeleteAccount } from '@/hooks/useUpdateProfile';
import { useSession, type SocialProvider } from '@/auth/SessionProvider';
import { brand } from '@/theme/tokens';
import { validatePasswordRule } from '@/utils/password-rule';

const LINKABLE: { key: SocialProvider; label: string }[] = [
  { key: 'google', label: 'Google' },
  { key: 'facebook', label: 'Facebook' },
  { key: 'github', label: 'GitHub' },
];

/**
 * Sécurité du compte — changement de mot de passe (POST
 * /auth/update-password) et suppression définitive (DELETE /my/account),
 * tous deux natifs. La liaison des comptes sociaux (Google/Facebook/
 * GitHub) nécessite un SDK provider natif + support backend dédié —
 * traitée dans un lot séparé.
 */
export default function SecurityScreen() {
  const router = useRouter();
  const insets = useSafeAreaInsets();
  const { isAuthenticated, signOut } = useSession();
  const me = useMe(isAuthenticated);
  const changePassword = useChangePassword();
  const deleteAccount = useDeleteAccount();
  const linkProvider = useLinkProvider();
  const unlinkProvider = useUnlinkProvider();
  const [pendingProvider, setPendingProvider] = useState<SocialProvider | null>(null);

  const linked = me.data?.linked_providers ?? [];

  const handleLink = (provider: SocialProvider) => {
    setPendingProvider(provider);
    linkProvider.mutate(provider, {
      onSuccess: (res) => {
        if (res.linked) {
          Alert.alert('Compte lié', 'Le compte a été lié avec succès.');
        } else if (res.error) {
          const msg =
            res.error === 'already_used'
              ? 'Ce compte est déjà lié à un autre utilisateur.'
              : 'La liaison a échoué. Réessayez.';
          Alert.alert('Liaison impossible', msg);
        }
      },
      onError: (err) => Alert.alert('Erreur', extractApiErrorMessage(err)),
      onSettled: () => setPendingProvider(null),
    });
  };

  const handleUnlink = (provider: SocialProvider, label: string) => {
    // Refus si c'est le dernier moyen de connexion.
    if (linked.length <= 1 && !me.data?.has_password) {
      Alert.alert(
        'Impossible de délier',
        'Définissez d\'abord un mot de passe ou liez un autre compte avant de délier celui-ci.',
      );
      return;
    }
    Alert.alert('Délier le compte', `Délier votre compte ${label} ?`, [
      { text: 'Annuler', style: 'cancel' },
      {
        text: 'Délier',
        style: 'destructive',
        onPress: () => {
          setPendingProvider(provider);
          unlinkProvider.mutate(provider, {
            onError: (err) => Alert.alert('Erreur', extractApiErrorMessage(err)),
            onSettled: () => setPendingProvider(null),
          });
        },
      },
    ]);
  };

  const [current, setCurrent] = useState('');
  const [next, setNext] = useState('');
  const [confirm, setConfirm] = useState('');
  const [deleteOpen, setDeleteOpen] = useState(false);

  const submitPassword = () => {
    if (current === '' || next === '' || confirm === '') {
      Alert.alert('Champs requis', 'Renseignez les trois champs.');
      return;
    }
    if (next !== confirm) {
      Alert.alert('Erreur', 'Le nouveau mot de passe et sa confirmation ne correspondent pas.');
      return;
    }
    const ruleError = validatePasswordRule(next);
    if (ruleError) {
      Alert.alert('Mot de passe trop faible', ruleError);
      return;
    }
    changePassword.mutate(
      { current_password: current, new_password: next, new_password_confirmation: confirm },
      {
        onSuccess: () => {
          setCurrent('');
          setNext('');
          setConfirm('');
          Alert.alert('Mot de passe', 'Votre mot de passe a été mis à jour.');
        },
        onError: (err) => Alert.alert('Erreur', extractApiErrorMessage(err)),
      },
    );
  };

  const runDelete = () => {
    deleteAccount.mutate(undefined, {
      onSuccess: () => {
        setDeleteOpen(false);
        signOut();
        router.replace('/(tabs)/home');
      },
      onError: (err) => Alert.alert('Erreur', extractApiErrorMessage(err)),
    });
  };

  if (!isAuthenticated) {
    return (
      <YStack flex={1} backgroundColor="$background" alignItems="center" justifyContent="center" padding="$5" gap={10}>
        <ShieldCheck size={36} color="$slate500" />
        <Paragraph fontSize={15} fontWeight="700" color="$slate900">
          Connectez-vous pour gérer votre sécurité
        </Paragraph>
        <Button backgroundColor="$brand" color="$brandText" onPress={() => router.push('/(auth)/login')}>
          Se connecter
        </Button>
      </YStack>
    );
  }

  return (
    <>
      <Stack.Screen options={{ headerShown: false }} />
      <YStack flex={1} backgroundColor="$background">
        <XStack
          paddingTop={insets.top + 8}
          paddingHorizontal={14}
          paddingBottom={10}
          alignItems="center"
          gap={10}
          borderBottomWidth={1}
          borderBottomColor="$borderColor"
        >
          <Pressable onPress={() => router.back()} hitSlop={8} accessibilityRole="button" accessibilityLabel="Retour">
            <YStack width={36} height={36} borderRadius={18} backgroundColor="$slate100" alignItems="center" justifyContent="center">
              <ArrowLeft size={18} color="$slate700" />
            </YStack>
          </Pressable>
          <H2 fontSize={20} fontWeight="700" color="$slate900" flex={1}>
            Sécurité
          </H2>
        </XStack>

        <ScrollView contentContainerStyle={{ padding: 16, gap: 22, paddingBottom: insets.bottom + 24 }} keyboardShouldPersistTaps="handled">
          <YStack gap={12}>
            <XStack alignItems="center" gap={8}>
              <KeyRound size={18} color={brand.primary} />
              <Paragraph fontSize={15} fontWeight="800" color="$slate900">
                Changer le mot de passe
              </Paragraph>
            </XStack>
            <YStack gap={4}>
              <Paragraph fontSize={12.5} color="$slate500" fontWeight="600">
                Mot de passe actuel
              </Paragraph>
              <PasswordInput value={current} onChangeText={setCurrent} autoComplete="current-password" textContentType="password" placeholder="••••••••" />
            </YStack>
            <YStack gap={4}>
              <Paragraph fontSize={12.5} color="$slate500" fontWeight="600">
                Nouveau mot de passe
              </Paragraph>
              <PasswordInput value={next} onChangeText={setNext} autoComplete="new-password" textContentType="newPassword" placeholder="8 caractères minimum" />
            </YStack>
            <YStack gap={4}>
              <Paragraph fontSize={12.5} color="$slate500" fontWeight="600">
                Confirmer le nouveau mot de passe
              </Paragraph>
              <PasswordInput value={confirm} onChangeText={setConfirm} autoComplete="new-password" textContentType="newPassword" placeholder="••••••••" />
            </YStack>
            <Button
              size="$4"
              backgroundColor="$brand"
              color="$brandText"
              fontWeight="800"
              borderRadius={12}
              marginTop={4}
              disabled={changePassword.isPending}
              icon={changePassword.isPending ? <Spinner color="white" /> : undefined}
              onPress={submitPassword}
            >
              Mettre à jour
            </Button>
          </YStack>

          <YStack height={1} backgroundColor="$borderColor" />

          <YStack gap={12}>
            <XStack alignItems="center" gap={8}>
              <Link2 size={18} color={brand.primary} />
              <Paragraph fontSize={15} fontWeight="800" color="$slate900">
                Comptes liés
              </Paragraph>
            </XStack>
            <Paragraph fontSize={13} color="$slate500" lineHeight={19}>
              Connectez des comptes sociaux pour vous connecter en un geste.
            </Paragraph>
            {LINKABLE.map(({ key, label }) => {
              const isLinked = linked.includes(key);
              const busy = pendingProvider === key && (linkProvider.isPending || unlinkProvider.isPending);
              return (
                <XStack
                  key={key}
                  alignItems="center"
                  gap={12}
                  padding={14}
                  borderRadius={12}
                  borderWidth={1}
                  borderColor="$borderColor"
                  backgroundColor="$background"
                >
                  {key === 'github' ? (
                    <Github size={20} color="$slate700" />
                  ) : (
                    <Paragraph fontSize={18} fontWeight="900" color={key === 'google' ? '#4285F4' : '#1877F2'}>
                      {key === 'google' ? 'G' : 'f'}
                    </Paragraph>
                  )}
                  <YStack flex={1}>
                    <Paragraph fontSize={14.5} fontWeight="700" color="$slate900">
                      {label}
                    </Paragraph>
                    <Paragraph fontSize={12} color={isLinked ? brand.success : '$slate500'}>
                      {isLinked ? 'Connecté' : 'Non connecté'}
                    </Paragraph>
                  </YStack>
                  <Pressable
                    onPress={() => (isLinked ? handleUnlink(key, label) : handleLink(key))}
                    disabled={busy}
                    hitSlop={6}
                    accessibilityRole="button"
                    accessibilityLabel={`${isLinked ? 'Délier' : 'Lier'} ${label}`}
                  >
                    <XStack
                      paddingHorizontal={14}
                      paddingVertical={8}
                      borderRadius={999}
                      backgroundColor={isLinked ? '$slate100' : '$brandAlpha10'}
                      alignItems="center"
                    >
                      {busy ? (
                        <Spinner size="small" color={brand.primary} />
                      ) : (
                        <Paragraph fontSize={13} fontWeight="700" color={isLinked ? '$slate700' : brand.primary}>
                          {isLinked ? 'Délier' : 'Lier'}
                        </Paragraph>
                      )}
                    </XStack>
                  </Pressable>
                </XStack>
              );
            })}
          </YStack>

          <YStack height={1} backgroundColor="$borderColor" />

          <YStack gap={10}>
            <XStack alignItems="center" gap={8}>
              <Trash2 size={18} color={brand.danger} />
              <Paragraph fontSize={15} fontWeight="800" color="$slate900">
                Zone de danger
              </Paragraph>
            </XStack>
            <Paragraph fontSize={13} color="$slate500" lineHeight={19}>
              La suppression de votre compte est définitive et efface toutes vos données.
            </Paragraph>
            <Pressable onPress={() => setDeleteOpen(true)} accessibilityRole="button" disabled={deleteAccount.isPending}>
              <XStack
                alignItems="center"
                justifyContent="center"
                gap={8}
                paddingVertical={13}
                borderRadius={12}
                borderWidth={1.5}
                borderColor={brand.danger}
              >
                {deleteAccount.isPending ? (
                  <Spinner color={brand.danger} />
                ) : (
                  <Trash2 size={16} color={brand.danger} />
                )}
                <Paragraph fontSize={14} fontWeight="800" color={brand.danger}>
                  Supprimer mon compte
                </Paragraph>
              </XStack>
            </Pressable>
          </YStack>
        </ScrollView>
      </YStack>

      <DeleteAccountModal
        open={deleteOpen}
        pending={deleteAccount.isPending}
        onCancel={() => setDeleteOpen(false)}
        onConfirm={runDelete}
      />
    </>
  );
}
