import { Check, MapPin, X } from '@tamagui/lucide-icons';
import { useState } from 'react';
import { Pressable, ScrollView, TextInput } from 'react-native';
import { Paragraph, XStack, YStack } from 'tamagui';

import { useCityAutocomplete } from '@/hooks/useCitiesAndTypes';
import { useDebounce } from '@/hooks/useDebounce';
import { brand } from '@/theme/tokens';

interface Props {
  /** Nom de ville actuellement sélectionné (affiché). */
  value: string;
  /** Appelé à la sélection — renvoie l'id ET le nom pour l'API (city_id). */
  onSelect: (city: { id: string; name: string }) => void;
  /** Appelé quand l'utilisateur efface la sélection. */
  onClear?: () => void;
  placeholder?: string;
}

/**
 * Champ ville avec autocomplete `/cities?q=…`. La sélection renvoie
 * `{ id, name }` — l'écran envoie `city_id` à l'API (le backend attend
 * un uuid `city_id`, pas un texte libre). Une ville déjà choisie
 * s'affiche en chip révocable ; taper rouvre les suggestions.
 */
export function CityAutocomplete({ value, onSelect, onClear, placeholder = 'Douala, Yaoundé…' }: Props) {
  const [query, setQuery] = useState('');
  const [editing, setEditing] = useState(false);
  const debounced = useDebounce(query, 300);
  const { data: suggestions } = useCityAutocomplete(editing ? debounced : '');

  if (value && !editing) {
    return (
      <XStack
        alignItems="center"
        gap={8}
        height={48}
        paddingHorizontal={14}
        borderWidth={1}
        borderColor="$borderColor"
        borderRadius={12}
        backgroundColor="$background"
      >
        <MapPin size={16} color={brand.primary} />
        <Paragraph flex={1} fontSize={15} color="$slate900" numberOfLines={1}>
          {value}
        </Paragraph>
        <Pressable
          onPress={() => {
            setQuery('');
            setEditing(true);
            onClear?.();
          }}
          hitSlop={8}
          accessibilityRole="button"
          accessibilityLabel="Changer de ville"
        >
          <X size={16} color={brand.slate500} />
        </Pressable>
      </XStack>
    );
  }

  const showSuggestions = query.trim().length >= 1 && (suggestions?.length ?? 0) > 0;

  return (
    <YStack position="relative" zIndex={20}>
      <XStack
        alignItems="center"
        gap={8}
        height={48}
        paddingHorizontal={14}
        borderWidth={1}
        borderColor="$borderColor"
        borderRadius={12}
        backgroundColor="$background"
      >
        <MapPin size={16} color="$slate500" />
        <TextInput
          value={query}
          onChangeText={setQuery}
          onFocus={() => setEditing(true)}
          placeholder={placeholder}
          placeholderTextColor={brand.slate500}
          autoCorrect={false}
          autoFocus={editing}
          style={{ flex: 1, fontSize: 15, color: brand.slate900 }}
          accessibilityLabel="Rechercher une ville"
        />
      </XStack>
      {showSuggestions ? (
        <YStack
          position="absolute"
          top={52}
          left={0}
          right={0}
          zIndex={30}
          backgroundColor="$background"
          borderWidth={1}
          borderColor="$borderColor"
          borderRadius={12}
          maxHeight={220}
          overflow="hidden"
          shadowColor="#000"
          shadowOpacity={0.12}
          shadowRadius={16}
          shadowOffset={{ width: 0, height: 8 }}
          elevation={8}
        >
          <ScrollView keyboardShouldPersistTaps="handled">
            {(suggestions ?? []).map((city) => (
              <Pressable
                key={city.id}
                onPress={() => {
                  onSelect({ id: city.id, name: city.name });
                  setQuery('');
                  setEditing(false);
                }}
                accessibilityRole="button"
                accessibilityLabel={city.name}
              >
                <XStack alignItems="center" gap={10} paddingHorizontal={14} paddingVertical={12}>
                  <MapPin size={15} color="$slate500" />
                  <Paragraph flex={1} fontSize={14} color="$slate900">
                    {city.name}
                  </Paragraph>
                  {city.name === value ? <Check size={15} color={brand.primary} /> : null}
                </XStack>
              </Pressable>
            ))}
          </ScrollView>
        </YStack>
      ) : null}
    </YStack>
  );
}
