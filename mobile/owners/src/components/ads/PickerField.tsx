import { Check, ChevronDown, Search, X } from '@tamagui/lucide-icons';
import { useMemo, useState } from 'react';
import { FlatList, Modal, Pressable, TextInput } from 'react-native';
import { H2, Paragraph, XStack, YStack } from 'tamagui';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

import { brand } from '@/theme/tokens';

export interface PickerOption {
  id: string;
  name: string;
}

/**
 * Labelled field that opens a searchable modal list of options. Used for
 * city / quarter / property-type selection in the ad form. Optionally
 * allows creating a new value (for missing cities/quarters).
 */
export function PickerField({
  label,
  value,
  options,
  onSelect,
  placeholder = 'Sélectionner…',
  searchable = true,
  disabled = false,
  onCreate,
  createLabel,
  error,
}: {
  label: string;
  value?: string;
  options: PickerOption[];
  onSelect: (option: PickerOption) => void;
  placeholder?: string;
  searchable?: boolean;
  disabled?: boolean;
  onCreate?: (name: string) => void;
  createLabel?: string;
  error?: string;
}) {
  const insets = useSafeAreaInsets();
  const [open, setOpen] = useState(false);
  const [query, setQuery] = useState('');

  const selected = options.find((o) => o.id === value);
  const filtered = useMemo(() => {
    const q = query.trim().toLowerCase();
    if (!q) return options;
    return options.filter((o) => o.name.toLowerCase().includes(q));
  }, [options, query]);

  return (
    <YStack gap={6}>
      <Paragraph fontSize={13} fontWeight="600" color="$slate500">
        {label}
      </Paragraph>
      <Pressable onPress={() => !disabled && setOpen(true)} disabled={disabled}>
        <XStack
          alignItems="center"
          justifyContent="space-between"
          height={48}
          paddingHorizontal={14}
          borderRadius={12}
          borderWidth={1}
          borderColor={error ? '$danger' : '$slate300'}
          backgroundColor={disabled ? '$slate100' : '$background'}
        >
          <Paragraph fontSize={15} color={selected ? '$slate900' : '$slate500'} flex={1} numberOfLines={1}>
            {selected?.name ?? placeholder}
          </Paragraph>
          <ChevronDown size={18} color={brand.slate500} />
        </XStack>
      </Pressable>
      {error ? (
        <Paragraph fontSize={12} color="$danger">
          {error}
        </Paragraph>
      ) : null}

      <Modal visible={open} animationType="slide" transparent onRequestClose={() => setOpen(false)}>
        <YStack flex={1} backgroundColor="rgba(0,0,0,0.4)" justifyContent="flex-end">
          <YStack
            backgroundColor="$background"
            borderTopLeftRadius={20}
            borderTopRightRadius={20}
            paddingTop={16}
            paddingBottom={insets.bottom + 16}
            maxHeight="80%"
          >
            <XStack alignItems="center" justifyContent="space-between" paddingHorizontal={20} marginBottom={12}>
              <H2 fontSize={18} fontWeight="800">
                {label}
              </H2>
              <Pressable onPress={() => setOpen(false)} hitSlop={10}>
                <X size={22} color={brand.slate700} />
              </Pressable>
            </XStack>

            {searchable ? (
              <XStack
                marginHorizontal={20}
                marginBottom={10}
                alignItems="center"
                gap={8}
                backgroundColor="$slate100"
                borderRadius={12}
                paddingHorizontal={12}
                height={42}
              >
                <Search size={16} color={brand.slate500} />
                <TextInput
                  value={query}
                  onChangeText={setQuery}
                  placeholder="Rechercher…"
                  placeholderTextColor={brand.slate500}
                  style={{ flex: 1, fontSize: 15, color: brand.slate900 }}
                  autoFocus
                />
              </XStack>
            ) : null}

            <FlatList
              data={filtered}
              keyExtractor={(o) => o.id}
              keyboardShouldPersistTaps="handled"
              contentContainerStyle={{ paddingHorizontal: 20 }}
              renderItem={({ item }) => {
                const active = item.id === value;
                return (
                  <Pressable
                    onPress={() => {
                      onSelect(item);
                      setOpen(false);
                      setQuery('');
                    }}
                  >
                    <XStack alignItems="center" justifyContent="space-between" paddingVertical={13} borderBottomWidth={0.5} borderBottomColor="$slate300">
                      <Paragraph fontSize={15} color="$slate900" fontWeight={active ? '800' : '500'}>
                        {item.name}
                      </Paragraph>
                      {active ? <Check size={18} color={brand.primary} /> : null}
                    </XStack>
                  </Pressable>
                );
              }}
              ListFooterComponent={
                onCreate && query.trim().length > 1 && filtered.length === 0 ? (
                  <Pressable
                    onPress={() => {
                      onCreate(query.trim());
                      setOpen(false);
                      setQuery('');
                    }}
                  >
                    <Paragraph fontSize={14} fontWeight="700" color="$brand" paddingVertical={16}>
                      + {createLabel ?? 'Créer'} « {query.trim()} »
                    </Paragraph>
                  </Pressable>
                ) : null
              }
            />
          </YStack>
        </YStack>
      </Modal>
    </YStack>
  );
}
