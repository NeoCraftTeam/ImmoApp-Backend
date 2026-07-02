import { BellPlus } from '@tamagui/lucide-icons';
import { useRouter } from 'expo-router';
import { Alert } from 'react-native';
import { Paragraph, Spinner, XStack } from 'tamagui';

import { extractApiErrorMessage } from '@/api/client';
import { useSession } from '@/auth/SessionProvider';
import { useCreateSearchAlert } from '@/hooks/useSearchAlerts';
import { brand } from '@/theme/tokens';
import type { Ad } from '@/types/ad';

interface Props {
  ad: Ad;
}

/**
 * Bouton « Créer une alerte » — réplique le SearchAlertButton web :
 * pré-remplit une alerte de recherche avec les critères de l'annonce
 * courante (ville, type, transaction, chambres) et la crée en un tap.
 * Redirige vers le login si l'utilisateur est anonyme.
 */
export function SearchAlertButton({ ad }: Props) {
  const router = useRouter();
  const { isAuthenticated } = useSession();
  const createAlert = useCreateSearchAlert();

  const cityName = ad.quarter?.city_name ?? null;
  const typeName = ad.type?.name ?? null;

  const handlePress = () => {
    if (!isAuthenticated) {
      Alert.alert(
        'Connexion requise',
        'Connectez-vous pour créer une alerte et être prévenu des nouvelles annonces.',
        [
          { text: 'Annuler', style: 'cancel' },
          { text: 'Se connecter', onPress: () => router.push('/(auth)/login') },
        ],
      );
      return;
    }

    const label = [typeName, cityName].filter(Boolean).join(' · ') || 'Nouvelle alerte';

    createAlert.mutate(
      {
        label,
        is_active: true,
        frequency: 'daily',
        filters: {
          city: cityName,
          type: typeName,
          transaction_type: ad.transaction_type ?? null,
          bedrooms: ad.bedrooms ?? null,
        },
        channels: { push: true, email: false },
      },
      {
        onSuccess: () => {
          Alert.alert('Alerte créée', 'Vous serez prévenu des nouvelles annonces correspondantes.', [
            { text: 'OK' },
            { text: 'Voir mes alertes', onPress: () => router.push('/search-alerts') },
          ]);
        },
        onError: (err) => {
          Alert.alert('Erreur', extractApiErrorMessage(err));
        },
      },
    );
  };

  return (
    <XStack
      alignItems="center"
      justifyContent="center"
      gap={8}
      height={48}
      borderRadius={12}
      borderWidth={1}
      borderColor={brand.primaryAlpha20}
      backgroundColor={brand.primaryAlpha10}
      pressStyle={{ opacity: 0.7 }}
      onPress={handlePress}
      accessibilityRole="button"
      accessibilityLabel="Créer une alerte de recherche"
      accessibilityState={{ busy: createAlert.isPending }}
    >
      {createAlert.isPending ? (
        <Spinner color={brand.primary} />
      ) : (
        <BellPlus size={17} color={brand.primary} />
      )}
      <Paragraph fontSize={14.5} fontWeight="700" color={brand.primary}>
        Créer une alerte pour ces critères
      </Paragraph>
    </XStack>
  );
}
