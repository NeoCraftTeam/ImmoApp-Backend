import { Award, Check, Sparkles } from '@tamagui/lucide-icons';
import { Alert, RefreshControl, ScrollView } from 'react-native';
import { Button, Paragraph, Spinner, XStack, YStack } from 'tamagui';

import { extractApiErrorMessage } from '@/api/client';
import { useSession } from '@/auth/SessionProvider';
import { EmptyState } from '@/components/EmptyState';
import { ScreenHeader } from '@/components/ScreenHeader';
import { useProServices, usePurchaseProService } from '@/hooks/useProServices';
import { brand } from '@/theme/tokens';
import { formatFcfa } from '@/utils/format';
import type { ProService } from '@/types/proservice';

function ServiceCard({ s, onPurchase }: { s: ProService; onPurchase: () => void }) {
  return (
    <YStack
      padding={16}
      gap={10}
      borderRadius={16}
      borderWidth={s.highlighted ? 2 : 1}
      borderColor={s.highlighted ? brand.primary : '$slate300'}
      backgroundColor={s.highlighted ? brand.primaryAlpha10 : '$background'}
    >
      <XStack alignItems="center" gap={10}>
        <YStack
          width={42}
          height={42}
          borderRadius={21}
          alignItems="center"
          justifyContent="center"
          backgroundColor={brand.primaryAlpha10}
        >
          <Sparkles size={20} color={brand.primary} />
        </YStack>
        <YStack flex={1} gap={2}>
          <Paragraph fontSize={15} fontWeight="800" color="$slate900">
            {s.name}
          </Paragraph>
          {s.duration_days ? (
            <Paragraph fontSize={11.5} color="$slate500">
              Valable {s.duration_days} jours
            </Paragraph>
          ) : null}
        </YStack>
        {s.price_credits != null ? (
          <Paragraph fontSize={14} fontWeight="800" color={brand.primary}>
            {s.price_credits} crédits
          </Paragraph>
        ) : s.price != null ? (
          <Paragraph fontSize={14} fontWeight="800" color={brand.primary}>
            {formatFcfa(s.price)}
          </Paragraph>
        ) : null}
      </XStack>

      {s.description ? (
        <Paragraph fontSize={12.5} color="$slate700">
          {s.description}
        </Paragraph>
      ) : null}

      <Button
        size="$3"
        marginTop={4}
        backgroundColor="$brand"
        color="white"
        fontWeight="700"
        borderRadius={10}
        onPress={onPurchase}
        icon={<Check size={14} color="white" />}
      >
        Souscrire
      </Button>
    </YStack>
  );
}

export default function ProServicesScreen() {
  const { isAuthenticated } = useSession();
  const { data: list = [], isLoading, isRefetching, refetch } = useProServices(isAuthenticated);
  const purchase = usePurchaseProService();

  const onPurchase = (s: ProService) => {
    Alert.alert(
      'Confirmer',
      `Souscrire au service « ${s.name} » ?`,
      [
        { text: 'Annuler', style: 'cancel' },
        {
          text: 'Souscrire',
          onPress: () =>
            purchase.mutate(
              { service_id: s.id },
              {
                onSuccess: () => Alert.alert('Succès', 'Service activé !'),
                onError: (err) => Alert.alert('Erreur', extractApiErrorMessage(err)),
              },
            ),
        },
      ],
    );
  };

  return (
    <YStack flex={1} backgroundColor="$background">
      <ScreenHeader title="Services Pro" subtitle="Boostez votre activité bailleur" />
      <ScrollView
        contentContainerStyle={{ padding: 16, paddingBottom: 32, gap: 12 }}
        refreshControl={
          <RefreshControl refreshing={isRefetching} onRefresh={refetch} tintColor={brand.primary} />
        }
      >
        {isLoading ? (
          <YStack height={320} alignItems="center" justifyContent="center">
            <Spinner color={brand.primary} size="large" />
          </YStack>
        ) : list.length === 0 ? (
          <YStack height={320}>
            <EmptyState
              icon={<Award size={28} color={brand.primary} />}
              title="Aucun service disponible"
              hint="De nouveaux services pro arriveront bientôt."
            />
          </YStack>
        ) : (
          list.map((s) => <ServiceCard key={s.id} s={s} onPurchase={() => onPurchase(s)} />)
        )}
      </ScrollView>
    </YStack>
  );
}
