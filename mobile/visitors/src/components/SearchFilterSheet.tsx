import { ChevronDown, ChevronUp } from '@tamagui/lucide-icons';
import { useEffect, useState } from 'react';
import { Button, Input, Paragraph, Sheet, XStack, YStack, Separator } from 'tamagui';

import { useAdFacets } from '@/hooks/useAdFacets';
import { usePropertyAttributeGroups } from '@/hooks/usePropertyAttributes';
import { brand } from '@/theme/tokens';
import { EMPTY_FILTERS, activeFilterCount, type AdFilters } from '@/types/filters';
import type { PropertyAttributeCategory } from '@/types/property-attribute';
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
  const { data: attributeGroups } = usePropertyAttributeGroups();
  const { data: facets } = useAdFacets();

  const bedroomsFacetLabel = (n: number): string => {
    const count = facets?.bedrooms?.find((b) => b.value === n)?.count;
    return count != null ? `${n}+ (${count})` : `${n}+`;
  };
  const parkingCount = facets?.has_parking?.with_parking;

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

  const toggleAttribute = (slug: string) => {
    setDraft((d) => ({
      ...d,
      attributes: d.attributes.includes(slug)
        ? d.attributes.filter((s) => s !== slug)
        : [...d.attributes, slug],
    }));
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
      snapPoints={[90]}
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

        <Sheet.ScrollView showsVerticalScrollIndicator={false} contentContainerStyle={{ gap: 16, paddingBottom: 8 }}>
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

        <YStack gap="$2">
          <Paragraph size="$3" color="$slate500">
            Chambres
          </Paragraph>
          <XStack gap="$2" flexWrap="wrap">
            {[null, 1, 2, 3, 4].map((n) => (
              <FilterChip
                key={`bed-${n ?? 'any'}`}
                label={n === null ? 'Toutes' : bedroomsFacetLabel(n)}
                active={draft.bedrooms === n}
                onPress={() => update('bedrooms', n)}
              />
            ))}
          </XStack>
        </YStack>

        <YStack gap="$2">
          <Paragraph size="$3" color="$slate500">
            Salles de bain
          </Paragraph>
          <XStack gap="$2" flexWrap="wrap">
            {[null, 1, 2, 3].map((n) => (
              <FilterChip
                key={`bath-${n ?? 'any'}`}
                label={n === null ? 'Toutes' : `${n}+`}
                active={draft.bathrooms === n}
                onPress={() => update('bathrooms', n)}
              />
            ))}
          </XStack>
        </YStack>

        {draft.transactionType === 'location' ? (
          <YStack gap="$2">
            <Paragraph size="$3" color="$slate500">
              Période
            </Paragraph>
            <XStack gap="$2">
              <FilterChip
                label="Toutes"
                active={draft.pricePeriod === null}
                onPress={() => update('pricePeriod', null)}
              />
              <FilterChip
                label="Mensuel"
                active={draft.pricePeriod === 'mois'}
                onPress={() => update('pricePeriod', 'mois')}
              />
              <FilterChip
                label="Journalier"
                active={draft.pricePeriod === 'jour'}
                onPress={() => update('pricePeriod', 'jour')}
              />
            </XStack>
          </YStack>
        ) : null}

        <YStack gap="$2">
          <Paragraph size="$3" color="$slate500">
            Options
          </Paragraph>
          <XStack gap="$2" flexWrap="wrap">
            <FilterChip
              label={parkingCount != null ? `Parking (${parkingCount})` : 'Parking'}
              active={draft.hasParking}
              onPress={() => update('hasParking', !draft.hasParking)}
            />
            <FilterChip
              label="Visite 3D"
              active={draft.has3dTour}
              onPress={() => update('has3dTour', !draft.has3dTour)}
            />
            <FilterChip
              label="Vérifiée"
              active={draft.isVerified}
              onPress={() => update('isVerified', !draft.isVerified)}
            />
          </XStack>
        </YStack>

        {attributeGroups && attributeGroups.length > 0 ? (
          <YStack gap="$2">
            <Paragraph size="$3" color="$slate500">
              {t('search.filters.amenities')}
            </Paragraph>
            <YStack gap="$2">
              {attributeGroups.map((category) => (
                <AmenityCategoryAccordion
                  key={category.id}
                  category={category}
                  selected={draft.attributes}
                  onToggle={toggleAttribute}
                />
              ))}
            </YStack>
          </YStack>
        ) : null}
        </Sheet.ScrollView>

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
      </Sheet.Frame>
    </Sheet>
  );
}

/**
 * One collapsible equipment category — mirrors the web's per-category
 * MUI accordion (`SearchFiltersDrawerContent`): count badge + brand
 * border when the category holds active selections, chip-toggle per
 * attribute inside.
 */
function AmenityCategoryAccordion({
  category,
  selected,
  onToggle,
}: {
  category: PropertyAttributeCategory;
  selected: string[];
  onToggle: (slug: string) => void;
}) {
  const [expanded, setExpanded] = useState(false);
  const activeCount = category.attributes.filter((attr) =>
    selected.includes(attr.value),
  ).length;
  const Chevron = expanded ? ChevronUp : ChevronDown;

  return (
    <YStack
      borderWidth={1}
      borderColor={activeCount > 0 ? '$brand' : '$slate100'}
      borderRadius={12}
      overflow="hidden"
    >
      <XStack
        alignItems="center"
        justifyContent="space-between"
        paddingVertical={12}
        paddingHorizontal={14}
        onPress={() => setExpanded((v) => !v)}
        pressStyle={{ backgroundColor: '$slate100' }}
        accessibilityRole="button"
        accessibilityState={{ expanded }}
      >
        <XStack alignItems="center" gap="$2" flex={1}>
          <Paragraph fontSize={14} fontWeight="600" color="$slate900">
            {category.name}
          </Paragraph>
          {activeCount > 0 ? (
            <Paragraph
              fontSize={11}
              fontWeight="700"
              color="$brandText"
              backgroundColor="$brand"
              borderRadius={999}
              paddingHorizontal={7}
              paddingVertical={1}
            >
              {activeCount}
            </Paragraph>
          ) : null}
        </XStack>
        <Chevron size={16} color={brand.slate500} />
      </XStack>
      {expanded ? (
        <XStack gap="$2" flexWrap="wrap" paddingHorizontal={14} paddingBottom={12}>
          {category.attributes.map((attr) => (
            <FilterChip
              key={attr.value}
              label={attr.label}
              active={selected.includes(attr.value)}
              onPress={() => onToggle(attr.value)}
            />
          ))}
        </XStack>
      ) : null}
    </YStack>
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
