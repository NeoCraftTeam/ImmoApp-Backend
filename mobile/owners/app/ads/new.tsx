import { YStack } from 'tamagui';

import { AdForm } from '@/components/ads/AdForm';
import { ScreenHeader } from '@/components/ScreenHeader';
import { t } from '@/i18n';

/** Create-ad screen — wraps the shared `AdForm` in create mode. */
export default function NewAdScreen() {
  return (
    <YStack flex={1} backgroundColor="$background">
      <ScreenHeader title={t('adForm.createTitle')} />
      <AdForm mode="create" />
    </YStack>
  );
}
