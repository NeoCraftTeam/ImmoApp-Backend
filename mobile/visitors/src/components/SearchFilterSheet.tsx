import { useEffect, useState } from 'react';
import { Button, Input, Paragraph, Sheet, XStack, YStack, Separator } from 'tamagui';

import { EMPTY_FILTERS, activeFilterCount, type AdFilters } from '@/types/filters';
import { t } from '@/i18n';

interface Props {
  open: boolean;
  onOpenChange: (next: boolean) => void;
  filters: AdFilters;
  onApply: (next: AdFilters) => void;
}

/**
 * Bottom-sheet filter editor. Tamagui's `Sheet` handles the snap
 * points + drag-to-dismiss + animated overlay for free. Local
 * state is initialised from props on open so an in-progress edit
 * doesn't pollute the parent when the user dismisses without
 * tapping "Appliquer".
 *
 * Snap point is `60%` of the viewport — enough room for the form
 * without covering the search input behind it, so the user can
 * still see what query they're filtering against.
 */
export function SearchFilterSheet({ open, onOpenChange, filters, onApply }: Props) {
  const [draft, setDraft] = useState<AdFilters>(filters);

  // Re-sync the draft each time the sheet opens — closing without
  // applying must not leak partial edits to a future open.
  useEffect(() => {
    if (open) setDraft(filters);
  }, [open, filters]);

  const update = <K extends keyof AdFilters>(key: K, value: AdFilters[K]) => {
    setDraft((d) => ({ ...d, [key]: value }));
  };

  const updateNumber = (key: 'minPrice' | 'maxPrice' | 'minSurface' | 'maxSurface', raw: string) => {
    const trimmed = raw.trim();
    if (trimmed === '') {
      update(key, null);
      return;
    }
    const parsed = Number(trimmed);
    if (!Number.isFinite(parsed) || parsed < 0) return;
    update(key, parsed);
  };

  const handleApply = () => {
    onApply(draft);
    onOpenChange(false);
  };

  const handleReset = () => {
    setDraft(EMPTY_FILTERS);
  };

  return (
    <Sheet
      modal
      open={open}
      onOpenChange={onOpenChange}
      snapPoints={[60]}
      dismissOnSnapToBottom
      animation="quick"
    >
      <Sheet.Overlay animation="lazy" enterStyle={{ opacity: 0 }} exitStyle={{ opacity: 0 }} />
      <Sheet.Handle />
      <Sheet.Frame padding="$4" gap="$3" backgroundColor="$background">
        <XStack justifyContent="space-between" alignItems="center">
          <Paragraph fontWeight="700" size="$6">
            {t('search.filters.title')}
          </Paragraph>
          <Button size="$2" chromeless onPress={handleReset}>
            {t('search.filters.reset')}
          </Button>
        </XStack>

        <Separator />

        <YStack gap="$2">
          <Paragraph size="$3" color="$slate500">
            {t('search.filters.transaction')}
          </Paragraph>
          <XStack gap="$2">
            <FilterChip
              label={t('search.filters.transactionAny')}
              active={draft.transactionType === null}
              onPress={() => update('transactionType', null)}
            />
            <FilterChip
              label={t('search.filters.transactionRent')}
              active={draft.transactionType === 'location'}
              onPress={() => update('transactionType', 'location')}
            />
            <FilterChip
              label={t('search.filters.transactionBuy')}
              active={draft.transactionType === 'vente'}
              onPress={() => update('transactionType', 'vente')}
            />
          </XStack>
        </YStack>

        <YStack gap="$2">
          <Paragraph size="$3" color="$slate500">
            {t('search.filters.price')}
          </Paragraph>
          <XStack gap="$2" alignItems="center">
            <Input
              flex={1}
              keyboardType="numeric"
              placeholder={t('search.filters.min')}
              value={draft.minPrice != null ? String(draft.minPrice) : ''}
              onChangeText={(v) => updateNumber('minPrice', v)}
              size="$3"
            />
            <Paragraph color="$slate500">—</Paragraph>
            <Input
              flex={1}
              keyboardType="numeric"
              placeholder={t('search.filters.max')}
              value={draft.maxPrice != null ? String(draft.maxPrice) : ''}
              onChangeText={(v) => updateNumber('maxPrice', v)}
              size="$3"
            />
            <Paragraph color="$slate500" size="$2">
              FCFA
            </Paragraph>
          </XStack>
        </YStack>

        <YStack gap="$2">
          <Paragraph size="$3" color="$slate500">
            {t('search.filters.surface')}
          </Paragraph>
          <XStack gap="$2" alignItems="center">
            <Input
              flex={1}
              keyboardType="numeric"
              placeholder={t('search.filters.min')}
              value={draft.minSurface != null ? String(draft.minSurface) : ''}
              onChangeText={(v) => updateNumber('minSurface', v)}
              size="$3"
            />
            <Paragraph color="$slate500">—</Paragraph>
            <Input
              flex={1}
              keyboardType="numeric"
              placeholder={t('search.filters.max')}
              value={draft.maxSurface != null ? String(draft.maxSurface) : ''}
              onChangeText={(v) => updateNumber('maxSurface', v)}
              size="$3"
            />
            <Paragraph color="$slate500" size="$2">
              m²
            </Paragraph>
          </XStack>
        </YStack>

        <YStack gap="$2">
          <Paragraph size="$3" color="$slate500">
            {t('search.filters.type')}
          </Paragraph>
          <Input
            value={draft.type ?? ''}
            onChangeText={(v) => update('type', v.trim() === '' ? null : v)}
            placeholder={t('search.filters.typePlaceholder')}
            size="$3"
            autoCapitalize="none"
          />
        </YStack>

        <YStack flex={1} justifyContent="flex-end" gap="$2">
          <Button
            size="$5"
            backgroundColor="$brand"
            color="$brandText"
            fontWeight="700"
            onPress={handleApply}
            accessibilityRole="button"
          >
            {t('search.filters.apply')} {activeFilterCount(draft) > 0 ? `(${activeFilterCount(draft)})` : ''}
          </Button>
        </YStack>
      </Sheet.Frame>
    </Sheet>
  );
}

function FilterChip({
  label,
  active,
  onPress,
}: {
  label: string;
  active: boolean;
  onPress: () => void;
}) {
  return (
    <Button
      size="$3"
      backgroundColor={active ? '$brand' : '$slate100'}
      color={active ? '$brandText' : '$slate700'}
      borderRadius={999}
      fontWeight="600"
      onPress={onPress}
      accessibilityRole="button"
      accessibilityState={{ selected: active }}
    >
      {label}
    </Button>
  );
}
