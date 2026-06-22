import { useState } from 'react';
import { ChevronRight, Fingerprint, Key, ShieldAlert, Smartphone } from '@tamagui/lucide-icons';
import { Alert, ScrollView } from 'react-native';
import { Button, Input, Paragraph, XStack, YStack } from 'tamagui';

import { extractApiErrorMessage } from '@/api/client';
import { useChangePassword } from '@/hooks/useProfile';
import { ScreenHeader } from '@/components/ScreenHeader';
import { brand } from '@/theme/tokens';

interface RowProps {
  icon: React.ReactNode;
  label: string;
  hint?: string;
  onPress?: () => void;
}

function Row({ icon, label, hint, onPress }: RowProps) {
  return (
    <Button
      chromeless
      onPress={onPress}
      paddingHorizontal={14}
      paddingVertical={14}
      borderRadius={14}
      borderWidth={1}
      borderColor="$slate300"
      pressStyle={{ backgroundColor: '$slate100' }}
    >
      <XStack flex={1} alignItems="center" gap={12}>
        {icon}
        <YStack flex={1} gap={2}>
          <Paragraph fontSize={14} fontWeight="700" color="$slate900">
            {label}
          </Paragraph>
          {hint ? (
            <Paragraph fontSize={11.5} color="$slate500">
              {hint}
            </Paragraph>
          ) : null}
        </YStack>
        <ChevronRight size={16} color={brand.slate500} />
      </XStack>
    </Button>
  );
}

export default function SecurityScreen() {
  const changePassword = useChangePassword();

  const [showPwd, setShowPwd] = useState(false);
  const [current, setCurrent] = useState('');
  const [next, setNext] = useState('');
  const [confirm, setConfirm] = useState('');

  const onSubmitPassword = () => {
    if (next.length < 8) {
      Alert.alert('Mot de passe trop court', 'Au moins 8 caractères.');
      return;
    }
    if (next !== confirm) {
      Alert.alert('Erreur', 'La confirmation ne correspond pas.');
      return;
    }
    changePassword.mutate(
      {
        current_password: current,
        new_password: next,
        new_password_confirmation: confirm,
      },
      {
        onSuccess: () => {
          setCurrent('');
          setNext('');
          setConfirm('');
          setShowPwd(false);
          Alert.alert('Succès', 'Mot de passe mis à jour.');
        },
        onError: (err) => Alert.alert('Erreur', extractApiErrorMessage(err)),
      },
    );
  };

  return (
    <YStack flex={1} backgroundColor="$background">
      <ScreenHeader title="Sécurité" subtitle="Mot de passe, 2FA, sessions" />
      <ScrollView contentContainerStyle={{ padding: 16, paddingBottom: 32, gap: 12 }}>
        <Row
          icon={<Key size={20} color={brand.primary} />}
          label="Changer mon mot de passe"
          hint="Au moins 8 caractères"
          onPress={() => setShowPwd((v) => !v)}
        />

        {showPwd ? (
          <YStack
            padding={14}
            gap={10}
            borderRadius={14}
            borderWidth={1}
            borderColor={brand.primaryAlpha20}
            backgroundColor={brand.primaryAlpha10}
          >
            <YStack gap={6}>
              <Paragraph fontSize={12.5} fontWeight="700" color="$slate700">
                Mot de passe actuel
              </Paragraph>
              <Input value={current} onChangeText={setCurrent} secureTextEntry />
            </YStack>
            <YStack gap={6}>
              <Paragraph fontSize={12.5} fontWeight="700" color="$slate700">
                Nouveau mot de passe
              </Paragraph>
              <Input value={next} onChangeText={setNext} secureTextEntry />
            </YStack>
            <YStack gap={6}>
              <Paragraph fontSize={12.5} fontWeight="700" color="$slate700">
                Confirmer
              </Paragraph>
              <Input value={confirm} onChangeText={setConfirm} secureTextEntry />
            </YStack>
            <Button
              size="$3"
              backgroundColor="$brand"
              color="white"
              fontWeight="800"
              borderRadius={10}
              onPress={onSubmitPassword}
              disabled={changePassword.isPending}
            >
              {changePassword.isPending ? 'Mise à jour…' : 'Mettre à jour'}
            </Button>
          </YStack>
        ) : null}

        <Row
          icon={<Smartphone size={20} color={brand.accent} />}
          label="Authentification à 2 facteurs"
          hint="Bientôt disponible — SMS / TOTP"
          onPress={() => Alert.alert('2FA', 'Bientôt disponible sur mobile. Activez la 2FA depuis le web en attendant.')}
        />

        <Row
          icon={<Fingerprint size={20} color={brand.secondary} />}
          label="Passkey & biométrie"
          hint="Bientôt disponible"
          onPress={() => Alert.alert('Passkey', 'Bientôt disponible sur mobile.')}
        />

        <Row
          icon={<ShieldAlert size={20} color={brand.warning} />}
          label="Sessions actives"
          hint="Voir les appareils connectés"
          onPress={() => Alert.alert('Sessions', 'Cette fonctionnalité arrive bientôt. En attendant, déconnectez tous les appareils depuis le web.')}
        />
      </ScrollView>
    </YStack>
  );
}
