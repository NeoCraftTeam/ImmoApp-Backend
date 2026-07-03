import { Check, ChevronDown, Search as SearchIcon } from '@tamagui/lucide-icons';
import { useMemo, useState } from 'react';
import { FlatList, Modal, Pressable, TextInput } from 'react-native';
import { Paragraph, XStack, YStack } from 'tamagui';

import { brand } from '@/theme/tokens';
import {
  COUNTRIES,
  splitPhoneNumber,
  type Country,
} from '@/utils/countries';

interface Props {
  /** Numéro complet avec indicatif (ex. `+237650000001`). */
  value: string;
  onChange: (next: string) => void;
  placeholder?: string;
  accessibilityLabel?: string;
  hasError?: boolean;
}

/**
 * Champ téléphone avec sélecteur de pays interactif : drapeau +
 * indicatif cliquables ouvrent un modal de recherche, la saisie
 * locale est recomposée en `+<indicatif><numéro>` vers le parent.
 * Aucune dépendance (drapeaux emoji).
 */
export function PhoneInput({
  value,
  onChange,
  placeholder = '6 12 34 56 78',
  accessibilityLabel = 'Numéro de téléphone',
  hasError,
}: Props) {
  const { country, local } = useMemo(() => splitPhoneNumber(value), [value]);
  const [pickerOpen, setPickerOpen] = useState(false);
  const [search, setSearch] = useState('');

  const filtered = useMemo(() => {
    const q = search.trim().toLowerCase();
    if (q === '') return COUNTRIES;
    return COUNTRIES.filter(
      (c) => c.name.toLowerCase().includes(q) || c.dial.includes(q),
    );
  }, [search]);

  const emit = (nextCountry: Country, nextLocal: string) => {
    const digits = nextLocal.replace(/[^\d\s-]/g, '');
    onChange(digits.trim() === '' ? '' : `${nextCountry.dial}${digits.replace(/[\s-]/g, '')}`);
  };

  const selectCountry = (c: Country) => {
    setPickerOpen(false);
    setSearch('');
    emit(c, local);
  };

  return (
    <>
      <XStack
        alignItems="center"
        borderWidth={1}
        borderColor={hasError ? '$red10' : '$borderColor'}
        borderRadius={12}
        height={48}
        overflow="hidden"
        backgroundColor="$background"
      >
        <Pressable
          onPress={() => setPickerOpen(true)}
          accessibilityRole="button"
          accessibilityLabel={`Pays : ${country.name} (${country.dial})`}
        >
          <XStack
            alignItems="center"
            gap={4}
            paddingHorizontal={10}
            height="100%"
            backgroundColor="$slate100"
          >
            <Paragraph fontSize={17}>{country.flag}</Paragraph>
            <Paragraph fontSize={13.5} fontWeight="700" color="$slate900">
              {country.dial}
            </Paragraph>
            <ChevronDown size={13} color="$slate500" />
          </XStack>
        </Pressable>
        <TextInput
          value={local}
          onChangeText={(v) => emit(country, v)}
          placeholder={placeholder}
          placeholderTextColor={brand.slate500}
          keyboardType="phone-pad"
          autoComplete="tel"
          textContentType="telephoneNumber"
          accessibilityLabel={accessibilityLabel}
          style={{
            flex: 1,
            height: '100%',
            paddingHorizontal: 12,
            fontSize: 15,
            color: brand.slate900,
          }}
        />
      </XStack>

      <Modal
        visible={pickerOpen}
        animationType="slide"
        transparent
        onRequestClose={() => setPickerOpen(false)}
      >
        <YStack flex={1} justifyContent="flex-end" backgroundColor="rgba(0,0,0,0.4)">
          <Pressable style={{ flex: 1 }} onPress={() => setPickerOpen(false)} />
          <YStack
            backgroundColor="$background"
            borderTopLeftRadius={24}
            borderTopRightRadius={24}
            paddingTop={16}
            paddingBottom={28}
            maxHeight="75%"
            gap={12}
          >
            <Paragraph fontSize={16} fontWeight="800" color="$slate900" paddingHorizontal={20}>
              Choisir un pays
            </Paragraph>
            <XStack
              alignItems="center"
              gap={8}
              marginHorizontal={20}
              paddingHorizontal={12}
              height={42}
              borderRadius={12}
              backgroundColor="$slate100"
            >
              <SearchIcon size={16} color="$slate500" />
              <TextInput
                value={search}
                onChangeText={setSearch}
                placeholder="Rechercher un pays ou un indicatif…"
                placeholderTextColor={brand.slate500}
                autoCorrect={false}
                accessibilityLabel="Rechercher un pays"
                style={{ flex: 1, fontSize: 14, color: brand.slate900 }}
              />
            </XStack>
            <FlatList
              data={filtered}
              keyExtractor={(item) => item.code}
              keyboardShouldPersistTaps="handled"
              renderItem={({ item }) => {
                const selected = item.code === country.code;
                return (
                  <Pressable
                    onPress={() => selectCountry(item)}
                    accessibilityRole="button"
                    accessibilityLabel={`${item.name} ${item.dial}`}
                  >
                    <XStack
                      alignItems="center"
                      gap={12}
                      paddingHorizontal={20}
                      paddingVertical={13}
                      backgroundColor={selected ? brand.primaryAlpha10 : 'transparent'}
                    >
                      <Paragraph fontSize={20}>{item.flag}</Paragraph>
                      <Paragraph fontSize={14.5} color="$slate900" flex={1}>
                        {item.name}
                      </Paragraph>
                      <Paragraph fontSize={13.5} fontWeight="700" color="$slate500">
                        {item.dial}
                      </Paragraph>
                      {selected ? <Check size={16} color={brand.primary} /> : null}
                    </XStack>
                  </Pressable>
                );
              }}
            />
          </YStack>
        </YStack>
      </Modal>
    </>
  );
}
