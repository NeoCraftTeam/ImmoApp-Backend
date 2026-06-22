import { useLocalSearchParams } from 'expo-router';
import { Spinner, YStack } from 'tamagui';

import { AdForm } from '@/components/ads/AdForm';
import { ScreenHeader } from '@/components/ScreenHeader';
import { useAd } from '@/hooks/useAd';
import { brand } from '@/theme/tokens';
import { t } from '@/i18n';

/** Edit-ad screen — loads the ad then wraps the shared `AdForm`. */
export default function EditAdScreen() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const { data: ad, isLoading } = useAd(id);

  return (
    <YStack flex={1} backgroundColor="$background">
      <ScreenHeader title={t('adForm.editTitle')} />
      {isLoading || !ad ? (
        <YStack flex={1} alignItems="center" justifyContent="center">
          <Spinner color={brand.primary} size="large" />
        </YStack>
      ) : (
        <AdForm mode="edit" ad={ad} />
      )}
    </YStack>
  );
}
