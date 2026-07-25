import { RefreshControl, type RefreshControlProps } from 'react-native';

import { brand } from '@/theme/tokens';

type Props = Pick<RefreshControlProps, 'refreshing' | 'onRefresh'>;

/**
 * Pull-to-refresh natif iOS/Android avec la couleur marque KeyHome.
 * À brancher sur `ScrollView` / `FlatList` via `refreshControl={…}`.
 */
export function KeyHomeRefreshControl({ refreshing, onRefresh }: Props) {
  return (
    <RefreshControl
      refreshing={refreshing}
      onRefresh={onRefresh}
      tintColor={brand.primary}
      colors={[brand.primary]}
      progressBackgroundColor="#ffffff"
    />
  );
}
