import { XStack, YStack } from 'tamagui';

import { Skeleton } from '@/components/Skeleton';

/**
 * Skeleton pour la page détail annonce. Matche grossièrement la
 * géométrie pour qu'il n'y ait pas de saut quand le vrai contenu
 * arrive :
 *   - Hero image full-bleed (aspect 4/3 sur mobile)
 *   - Title block (titre + sous-ligne + prix)
 *   - Chips (3 attributs)
 *   - Bloc description (4 lignes)
 *   - Map placeholder (180 px de haut)
 *   - 2 cards review-like
 */
export function AdDetailSkeleton() {
  return (
    <YStack gap={18} paddingBottom={120}>
      {/* Hero */}
      <Skeleton width="100%" height={280} radius={0} />

      <YStack paddingHorizontal={16} gap={18}>
        {/* Title + price */}
        <YStack gap={8}>
          <Skeleton width="85%" height={22} radius={8} />
          <Skeleton width="55%" height={14} radius={6} />
          <Skeleton width="40%" height={28} radius={8} />
        </YStack>

        {/* Chips */}
        <XStack gap={8}>
          <Skeleton width={92} height={28} radius={14} />
          <Skeleton width={108} height={28} radius={14} />
          <Skeleton width={80} height={28} radius={14} />
        </XStack>

        {/* Description */}
        <YStack gap={8}>
          <Skeleton width="100%" height={12} />
          <Skeleton width="92%" height={12} />
          <Skeleton width="96%" height={12} />
          <Skeleton width="72%" height={12} />
        </YStack>

        {/* Map */}
        <Skeleton width="100%" height={180} radius={14} />

        {/* Reviews-ish */}
        <YStack gap={12}>
          <Skeleton width="40%" height={18} />
          <YStack gap={10}>
            <ReviewRow />
            <ReviewRow />
          </YStack>
        </YStack>
      </YStack>
    </YStack>
  );
}

function ReviewRow() {
  return (
    <YStack
      padding={14}
      gap={8}
      borderRadius={14}
      borderWidth={1}
      borderColor="$borderColor"
    >
      <XStack alignItems="center" gap={10}>
        <Skeleton width={36} height={36} radius={18} />
        <YStack flex={1} gap={4}>
          <Skeleton width="50%" height={12} />
          <Skeleton width="30%" height={10} />
        </YStack>
        <Skeleton width={72} height={14} radius={8} />
      </XStack>
      <YStack gap={6}>
        <Skeleton width="100%" height={10} />
        <Skeleton width="84%" height={10} />
      </YStack>
    </YStack>
  );
}
