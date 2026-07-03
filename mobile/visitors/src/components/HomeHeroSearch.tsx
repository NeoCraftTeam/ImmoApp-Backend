import AsyncStorage from '@react-native-async-storage/async-storage';
import {
  LocateFixed,
  MapPin,
  Search as SearchIcon,
  Sparkles,
} from '@tamagui/lucide-icons';
import { useRouter } from 'expo-router';
import { useState } from 'react';
import {
  ActivityIndicator,
  Alert,
  Keyboard,
  KeyboardAvoidingView,
  Modal,
  Platform,
  Pressable,
  ScrollView,
  TextInput,
} from 'react-native';
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
  const [aiModalOpen, setAiModalOpen] = useState(false);
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
      setAiModalOpen(false);
      goToSearch(Object.keys(params).length > 0 ? params : { q: query });
    } catch {
      setAiModalOpen(false);
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
        <Pressable
          onPress={() => setAiModalOpen(true)}
          accessibilityRole="button"
          accessibilityLabel="Ouvrir la recherche en langage naturel"
        >
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
            <Paragraph flex={1} fontSize={14} color="$slate500" numberOfLines={1}>
              {aiInput.trim() !== ''
                ? aiInput
                : 'Décrivez ce que vous cherchez…'}
            </Paragraph>
            <SearchIcon size={18} color={brand.primary} />
          </XStack>
        </Pressable>
      )}

      {/* Popup animé de saisie IA — grande zone de texte + exemples. */}
      <Modal
        visible={aiModalOpen}
        transparent
        animationType="slide"
        onRequestClose={() => setAiModalOpen(false)}
      >
        <KeyboardAvoidingView
          style={{ flex: 1 }}
          behavior={Platform.OS === 'ios' ? 'padding' : undefined}
        >
          <YStack flex={1} justifyContent="flex-end" backgroundColor="rgba(0,0,0,0.45)">
            <Pressable style={{ flex: 1 }} onPress={() => setAiModalOpen(false)} />
            <YStack
              backgroundColor="$background"
              borderTopLeftRadius={24}
              borderTopRightRadius={24}
              paddingHorizontal={20}
              paddingTop={18}
              paddingBottom={30}
              gap={14}
            >
              <XStack alignItems="center" gap={8}>
                <Sparkles size={20} color={brand.primary} />
                <Paragraph fontSize={17} fontWeight="800" color="$slate900" flex={1}>
                  Recherche intelligente
                </Paragraph>
                <Pressable
                  onPress={() => setAiModalOpen(false)}
                  hitSlop={8}
                  accessibilityRole="button"
                  accessibilityLabel="Fermer"
                >
                  <Paragraph fontSize={13} fontWeight="700" color="$slate500">
                    Fermer
                  </Paragraph>
                </Pressable>
              </XStack>
              <Paragraph fontSize={13} color="$slate500" lineHeight={19}>
                Décrivez votre recherche en langage naturel — type de bien, ville,
                quartier, budget, chambres… L'IA la traduit en filtres précis.
              </Paragraph>
              <TextInput
                value={aiInput}
                onChangeText={setAiInput}
                placeholder="Ex : Appartement 3 pièces à Bastos moins de 150 000 FCFA par mois…"
                placeholderTextColor={brand.slate500}
                multiline
                autoFocus
                editable={!parse.isPending}
                style={{
                  minHeight: 110,
                  maxHeight: 180,
                  borderWidth: 1.5,
                  borderColor: brand.primaryAlpha20,
                  borderRadius: 16,
                  padding: 14,
                  fontSize: 16,
                  lineHeight: 22,
                  color: brand.slate900,
                  backgroundColor: brand.primaryAlpha10,
                  textAlignVertical: 'top',
                }}
                accessibilityLabel="Recherche en langage naturel"
              />
              <XStack gap="$2" flexWrap="wrap">
                {AI_EXAMPLES.map((example) => (
                  <Pressable
                    key={example}
                    onPress={() => setAiInput(example)}
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
              <Pressable
                onPress={() => void runAiSearch(aiInput)}
                disabled={parse.isPending || aiInput.trim() === ''}
                accessibilityRole="button"
                accessibilityLabel="Lancer la recherche IA"
              >
                <XStack
                  alignItems="center"
                  justifyContent="center"
                  gap={8}
                  paddingVertical={14}
                  borderRadius={14}
                  backgroundColor={aiInput.trim() ? brand.primary : brand.slate300}
                >
                  {parse.isPending ? (
                    <ActivityIndicator size="small" color="white" />
                  ) : (
                    <SearchIcon size={18} color="white" />
                  )}
                  <Paragraph fontSize={15} fontWeight="800" color="white">
                    {parse.isPending ? 'Analyse en cours…' : 'Rechercher'}
                  </Paragraph>
                </XStack>
              </Pressable>
            </YStack>
          </YStack>
        </KeyboardAvoidingView>
      </Modal>
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
