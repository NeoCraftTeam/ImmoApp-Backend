import { Rocket, X, Zap } from '@tamagui/lucide-icons';
import { useRouter } from 'expo-router';
import { useState } from 'react';
import { Alert, Modal, Pressable, ScrollView } from 'react-native';
import { Button, H2, Paragraph, Spinner, XStack, YStack } from 'tamagui';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

import { extractApiErrorMessage } from '@/api/client';
import { useApplyBoost, useBoostPacks, useCreditsBalance } from '@/hooks/useBoost';
import { brand } from '@/theme/tokens';
import { formatCompact } from '@/utils/format';
import { t } from '@/i18n';

/**
 * Bottom-sheet modal to boost an ad. Lists the available boost packs +
 * the owner's credit balance, applies the chosen pack, and routes to the
 * credit-purchase flow when the balance is too low.
 */
export function BoostSheet({
  adId,
  open,
  onClose,
  onBoosted,
}: {
  adId: string;
  open: boolean;
  onClose: () => void;
  onBoosted?: () => void;
}) {
  const insets = useSafeAreaInsets();
  const router = useRouter();
  const packs = useBoostPacks();
  const balance = useCreditsBalance(open);
  const applyBoost = useApplyBoost(adId);
  const [selected, setSelected] = useState<string | null>(null);

  const chosen = packs.data?.find((p) => p.id === selected);
  const canAfford = chosen ? (balance.data ?? 0) >= chosen.price_credits : true;

  const handleBoost = () => {
    if (!chosen) return;
    if (!canAfford) {
      Alert.alert(t('boost.insufficient'), t('boost.buyCredits'), [
        { text: t('common.cancel'), style: 'cancel' },
        { text: t('boost.buyCredits'), onPress: () => { onClose(); router.push('/subscriptions' as never); } },
      ]);
      return;
    }
    applyBoost.mutate(chosen.id, {
      onSuccess: () => {
        onBoosted?.();
        onClose();
        Alert.alert(t('boost.active'));
      },
      onError: (err) => Alert.alert(t('common.error'), extractApiErrorMessage(err)),
    });
  };

  return (
    <Modal visible={open} animationType="slide" transparent onRequestClose={onClose}>
      <YStack flex={1} backgroundColor="rgba(0,0,0,0.4)" justifyContent="flex-end">
        <YStack
          backgroundColor="$background"
          borderTopLeftRadius={22}
          borderTopRightRadius={22}
          paddingTop={18}
          paddingBottom={insets.bottom + 16}
          maxHeight="86%"
        >
          <XStack alignItems="center" justifyContent="space-between" paddingHorizontal={20} marginBottom={6}>
            <XStack alignItems="center" gap={8}>
              <Rocket size={20} color={brand.accent} />
              <H2 fontSize={19} fontWeight="900">
                {t('boost.title')}
              </H2>
            </XStack>
            <Pressable onPress={onClose} hitSlop={10}>
              <X size={22} color={brand.slate700} />
            </Pressable>
          </XStack>
          <Paragraph fontSize={13} color="$slate500" paddingHorizontal={20} marginBottom={12}>
            {t('boost.subtitle')}
          </Paragraph>

          <XStack paddingHorizontal={20} marginBottom={12} alignItems="center" gap={8}>
            <Zap size={16} color={brand.accent} />
            <Paragraph fontSize={13} color="$slate700" fontWeight="700">
              {t('boost.yourBalance')} : {formatCompact(balance.data)} {t('boost.credits')}
            </Paragraph>
          </XStack>

          <ScrollView contentContainerStyle={{ paddingHorizontal: 20, gap: 10 }}>
            {packs.isLoading ? (
              <YStack paddingVertical={24} alignItems="center">
                <Spinner color={brand.primary} />
              </YStack>
            ) : (
              (packs.data ?? []).map((pack) => {
                const active = selected === pack.id;
                return (
                  <Pressable key={pack.id} onPress={() => setSelected(pack.id)}>
                    <XStack
                      padding={14}
                      borderRadius={14}
                      borderWidth={1.5}
                      borderColor={active ? brand.primary : brand.slate300}
                      backgroundColor={active ? brand.primaryAlpha10 : '$background'}
                      alignItems="center"
                      justifyContent="space-between"
                    >
                      <YStack flex={1} gap={3}>
                        <XStack alignItems="center" gap={8}>
                          <Paragraph fontSize={15} fontWeight="800" color="$slate900">
                            {pack.name}
                          </Paragraph>
                          {pack.is_popular ? (
                            <XStack backgroundColor={brand.accent} paddingHorizontal={7} paddingVertical={2} borderRadius={6}>
                              <Paragraph fontSize={9} fontWeight="800" color="white">
                                {t('boost.popular').toUpperCase()}
                              </Paragraph>
                            </XStack>
                          ) : null}
                        </XStack>
                        <Paragraph fontSize={12} color="$slate500">
                          {pack.duration_days} {t('boost.duration')} · {t('boost.score')} +{pack.boost_score}
                        </Paragraph>
                      </YStack>
                      <Paragraph fontSize={15} fontWeight="900" color="$brand">
                        {formatCompact(pack.price_credits)} {t('boost.credits')}
                      </Paragraph>
                    </XStack>
                  </Pressable>
                );
              })
            )}
          </ScrollView>

          <YStack paddingHorizontal={20} marginTop={14}>
            <Button
              size="$5"
              backgroundColor={chosen ? '$brand' : '$slate300'}
              color="white"
              fontWeight="800"
              borderRadius={14}
              disabled={!chosen || applyBoost.isPending}
              icon={applyBoost.isPending ? <Spinner color="white" /> : <Rocket size={18} color="white" />}
              onPress={handleBoost}
            >
              {chosen && !canAfford ? t('boost.buyCredits') : t('boost.confirm')}
            </Button>
          </YStack>
        </YStack>
      </YStack>
    </Modal>
  );
}
