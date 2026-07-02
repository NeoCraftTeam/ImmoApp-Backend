import AsyncStorage from '@react-native-async-storage/async-storage';
import {
  LocateFixed,
  MapPin,
  Search as SearchIcon,
  Sparkles,
} from '@tamagui/lucide-icons';
import { useRouter } from 'expo-router';
import { useState } from 'react';
import { ActivityIndicator, Alert, Keyboard, Pressable, ScrollView } from 'react-native';
import { Input, Paragraph, XStack, YStack } from 'tamagui';

import { useCityAutocomplete } from '@/hooks/useCitiesAndTypes';
import { useDebounce } from '@/hooks/useDebounce';
import { useNaturalSearchParse } from '@/hooks/useNaturalSearch';
import { brand } from '@/theme/tokens';
import { parsedToSearchParams } from '@/utils/nlp-search';

/** Même clé que le web (`kh:last-intent`) : mémorise louer/acheter. */
const INTENT_KEY = 'kh:last-intent';

const AI_EXAMPLES = [
  'Studio meublé à Douala',
  'Appartement 2 chambres à Bastos',
  'Terrain à vendre à Yaoundé',
];

type Mode = 'city' | 'ai';

/**
 * Hero search de la Home — port mobile du `HeroSearch` web : deux
 * onglets « Par ville » (autocomplete villes + géolocalisation) et
 * « Recherche IA » (langage naturel via `POST /search/parse`). Les
 * deux débouchent sur l'onglet recherche, préchargé via params.
 */
export function HomeHeroSearch() {
  const router = useRouter();
  const [mode, setMode] = useState<Mode>('city');
  const [cityInput, setCityInput] = useState('');
  const [aiInput, setAiInput] = useState('');
  const debouncedCity = useDebounce(cityInput, 300);
  const { data: suggestions } = useCityAutocomplete(mode === 'city' ? debouncedCity : '');
  const parse = useNaturalSearchParse();

  const goToSearch = (params: Record<string, string>) => {
    Keyboard.dismiss();
    router.push({ pathname: '/(tabs)/search', params });
  };

  const navigateWithIntent = async (cityName: string) => {
    setCityInput('');
    const stored = await AsyncStorage.getItem(INTENT_KEY).catch(() => null);
    if (stored === 'louer' || stored === 'acheter') {
      goToSearch({
        city: cityName,
        transaction_type: stored === 'louer' ? 'location' : 'vente',
      });
      return;
    }
    Alert.alert(`Que recherchez-vous à ${cityName} ?`, undefined, [
      {
        text: 'À louer',
        onPress: () => {
          void AsyncStorage.setItem(INTENT_KEY, 'louer').catch(() => {});
          goToSearch({ city: cityName, transaction_type: 'location' });
        },
      },
      {
        text: 'À acheter',
        onPress: () => {
          void AsyncStorage.setItem(INTENT_KEY, 'acheter').catch(() => {});
          goToSearch({ city: cityName, transaction_type: 'vente' });
        },
      },
      { text: 'Voir tout', onPress: () => goToSearch({ city: cityName }) },
    ]);
  };

  const runAiSearch = async (raw: string) => {
    const query = raw.trim();
    if (query === '' || parse.isPending) return;
    try {
      const parsed = await parse.mutateAsync(query);
      const params = parsedToSearchParams(parsed);
      goToSearch(Object.keys(params).length > 0 ? params : { q: query });
    } catch {
      goToSearch({ q: query });
    }
  };

  const showSuggestions =
    mode === 'city' && cityInput.trim().length >= 2 && (suggestions?.length ?? 0) > 0;

  return (
    <YStack gap="$2" marginBottom={18} zIndex={30}>
      <XStack gap="$2">
        <ModeChip
          label="Par ville"
          icon={<MapPin size={13} color={mode === 'city' ? brand.primaryText : brand.slate700} />}
          active={mode === 'city'}
          onPress={() => setMode('city')}
        />
        <ModeChip
          label="Recherche IA"
          icon={<Sparkles size={13} color={mode === 'ai' ? brand.primaryText : brand.slate700} />}
          active={mode === 'ai'}
          onPress={() => setMode('ai')}
        />
      </XStack>

      {mode === 'city' ? (
        <YStack position="relative" zIndex={30}>
          <XStack gap="$2" alignItems="center">
            <Pressable
              onPress={() => router.push('/nearby')}
              hitSlop={6}
              accessibilityRole="button"
              accessibilityLabel="Rechercher autour de moi"
            >
              <YStack
                width={44}
                height={44}
                borderRadius={12}
                borderWidth={1}
                borderColor="$borderColor"
                alignItems="center"
                justifyContent="center"
              >
                <LocateFixed size={18} color={brand.primary} />
              </YStack>
            </Pressable>
            <XStack
              flex={1}
              alignItems="center"
              gap="$2"
              paddingHorizontal="$3"
              borderWidth={1}
              borderColor="$borderColor"
              borderRadius={12}
              height={44}
              backgroundColor="$background"
            >
              <SearchIcon size={18} color="$slate500" />
              <Input
                flex={1}
                value={cityInput}
                onChangeText={setCityInput}
                placeholder="Ville, quartier…"
                placeholderTextColor="$slate500"
                autoCapitalize="none"
                autoCorrect={false}
                unstyled
                size="$4"
                accessibilityLabel="Ville, quartier…"
              />
            </XStack>
          </XStack>

          {showSuggestions && (
            <YStack
              position="absolute"
              top={50}
              left={52}
              right={0}
              zIndex={40}
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
                    onPress={() => void navigateWithIntent(city.name)}
                    accessibilityRole="button"
                    accessibilityLabel={`Rechercher à ${city.name}`}
                  >
                    <XStack alignItems="center" gap={10} paddingHorizontal={14} paddingVertical={12}>
                      <MapPin size={16} color="$slate500" />
                      <Paragraph fontSize={14} color="$slate900">
                        {city.name}
                      </Paragraph>
                    </XStack>
                  </Pressable>
                ))}
              </ScrollView>
            </YStack>
          )}
        </YStack>
      ) : (
        <YStack gap="$2">
          <XStack
            alignItems="center"
            gap="$2"
            paddingHorizontal="$3"
            borderWidth={1}
            borderColor="$borderColor"
            borderRadius={12}
            minHeight={44}
            backgroundColor="$background"
          >
            <Sparkles size={18} color={brand.primary} />
            <Input
              flex={1}
              value={aiInput}
              onChangeText={setAiInput}
              onSubmitEditing={() => void runAiSearch(aiInput)}
              returnKeyType="search"
              placeholder="Ex : Appartement 3 pièces à Bastos moins de 150 000 FCFA…"
              placeholderTextColor="$slate500"
              unstyled
              size="$4"
              accessibilityLabel="Recherche en langage naturel"
            />
            {parse.isPending ? (
              <ActivityIndicator size="small" />
            ) : (
              <Pressable
                onPress={() => void runAiSearch(aiInput)}
                hitSlop={8}
                accessibilityRole="button"
                accessibilityLabel="Lancer la recherche IA"
              >
                <SearchIcon size={18} color={brand.primary} />
              </Pressable>
            )}
          </XStack>
          <XStack gap="$2" flexWrap="wrap">
            {AI_EXAMPLES.map((example) => (
              <Pressable
                key={example}
                onPress={() => {
                  setAiInput(example);
                  void runAiSearch(example);
                }}
                accessibilityRole="button"
              >
                <XStack
                  paddingHorizontal={10}
                  paddingVertical={6}
                  borderRadius={999}
                  backgroundColor="$slate100"
                >
                  <Paragraph fontSize={12} color="$slate700">
                    {example}
                  </Paragraph>
                </XStack>
              </Pressable>
            ))}
          </XStack>
        </YStack>
      )}
    </YStack>
  );
}

function ModeChip({
  label,
  icon,
  active,
  onPress,
}: {
  label: string;
  icon: React.ReactNode;
  active: boolean;
  onPress: () => void;
}) {
  return (
    <Pressable onPress={onPress} hitSlop={4} accessibilityRole="button" accessibilityState={{ selected: active }}>
      <XStack
        alignItems="center"
        gap={6}
        paddingHorizontal={12}
        paddingVertical={7}
        borderRadius={999}
        backgroundColor={active ? '$brand' : '$slate100'}
      >
        {icon}
        <Paragraph fontSize={12.5} fontWeight="700" color={active ? '$brandText' : '$slate700'}>
          {label}
        </Paragraph>
      </XStack>
    </Pressable>
  );
}
