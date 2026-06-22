import { Star } from '@tamagui/lucide-icons';
import { YStack } from 'tamagui';

import { EmptyState } from '@/components/EmptyState';
import { ScreenHeader } from '@/components/ScreenHeader';
import { t } from '@/i18n';
import { brand } from '@/theme/tokens';

export default function ReviewsScreen() {
  return (
    <YStack flex={1} backgroundColor="$background">
      <ScreenHeader title={t('reviews.title')} />

      <YStack flex={1} justifyContent="center">
        <EmptyState
          icon={<Star size={28} color={brand.accent} />}
          title={t('reviews.empty')}
          hint="Les avis laissés par les locataires sur vos annonces apparaîtront ici. Encouragez vos locataires satisfaits à partager leur expérience pour renforcer la confiance des futurs prospects."
        />
      </YStack>
    </YStack>
  );
}
