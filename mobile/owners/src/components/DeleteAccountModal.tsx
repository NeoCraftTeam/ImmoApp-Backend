import { AlertTriangle } from '@tamagui/lucide-icons';
import { useState } from 'react';
import { Modal, Pressable, TextInput } from 'react-native';
import { H2, Paragraph, Spinner, XStack, YStack } from 'tamagui';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

import { brand } from '@/theme/tokens';

/** Phrase exacte exigée par le backend (GdprController). */
export const DELETE_ACCOUNT_CONFIRMATION = 'SUPPRIMER MON COMPTE';

/**
 * Confirmation de suppression de compte à friction élevée : l'utilisateur
 * doit taper la phrase exacte (comme sur le web) avant que le bouton
 * destructif ne s'active — un simple tap d'Alert ne suffit plus à
 * effacer un compte sur un appareil déverrouillé prêté/volé.
 */
export function DeleteAccountModal({
  open,
  pending,
  onCancel,
  onConfirm,
}: {
  open: boolean;
  pending: boolean;
  onCancel: () => void;
  onConfirm: () => void;
}) {
  const insets = useSafeAreaInsets();
  const [phrase, setPhrase] = useState('');
  const matches = phrase.trim().toUpperCase() === DELETE_ACCOUNT_CONFIRMATION;

  const close = () => {
    if (pending) return;
    setPhrase('');
    onCancel();
  };

  return (
    <Modal visible={open} transparent animationType="fade" onRequestClose={close}>
      <YStack flex={1} justifyContent="center" backgroundColor="rgba(0,0,0,0.5)" padding={24}>
        <Pressable style={{ position: 'absolute', inset: 0 }} onPress={close} />
        <YStack
          backgroundColor="$background"
          borderRadius={20}
          padding={22}
          gap={14}
          marginBottom={insets.bottom}
        >
          <XStack alignItems="center" gap={10}>
            <YStack width={44} height={44} borderRadius={22} backgroundColor={`${brand.danger}1A`} alignItems="center" justifyContent="center">
              <AlertTriangle size={22} color={brand.danger} />
            </YStack>
            <H2 fontSize={18} fontWeight="800" color="$slate900" flex={1}>
              Supprimer le compte
            </H2>
          </XStack>

          <Paragraph fontSize={14} color="$slate700" lineHeight={20}>
            Cette action est définitive : votre compte, vos annonces et vos
            données seront supprimés. Pour confirmer, tapez exactement :
          </Paragraph>
          <Paragraph fontSize={14} fontWeight="800" color="$slate900">
            {DELETE_ACCOUNT_CONFIRMATION}
          </Paragraph>

          <TextInput
            value={phrase}
            onChangeText={setPhrase}
            autoCapitalize="characters"
            autoCorrect={false}
            editable={!pending}
            placeholder={DELETE_ACCOUNT_CONFIRMATION}
            placeholderTextColor={brand.slate500}
            style={{
              borderWidth: 1.5,
              borderColor: matches ? brand.danger : brand.slate300,
              borderRadius: 12,
              paddingHorizontal: 14,
              paddingVertical: 12,
              fontSize: 15,
              color: brand.slate900,
            }}
          />

          <Pressable
            onPress={matches && !pending ? onConfirm : undefined}
            disabled={!matches || pending}
            accessibilityRole="button"
          >
            <XStack
              backgroundColor={matches ? brand.danger : brand.slate300}
              paddingVertical={14}
              borderRadius={14}
              alignItems="center"
              justifyContent="center"
              gap={8}
              opacity={matches ? 1 : 0.7}
            >
              {pending ? <Spinner color="white" /> : null}
              <Paragraph color="white" fontWeight="800" fontSize={15}>
                Supprimer définitivement
              </Paragraph>
            </XStack>
          </Pressable>
          <Pressable onPress={close} disabled={pending} accessibilityRole="button">
            <XStack paddingVertical={10} alignItems="center" justifyContent="center">
              <Paragraph color="$slate500" fontWeight="700" fontSize={14}>
                Annuler
              </Paragraph>
            </XStack>
          </Pressable>
        </YStack>
      </YStack>
    </Modal>
  );
}
