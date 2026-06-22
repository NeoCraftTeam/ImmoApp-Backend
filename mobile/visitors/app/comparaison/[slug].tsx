import { ArrowLeft, ExternalLink } from '@tamagui/lucide-icons';
import { Stack, useLocalSearchParams, useRouter } from 'expo-router';
import { Linking, Pressable, ScrollView } from 'react-native';
import { H2, Paragraph, XStack, YStack } from 'tamagui';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

import { brand } from '@/theme/tokens';

interface ComparisonPage {
  title: string;
  intro: string;
  labelA: string;
  labelB: string;
  rows: { criterion: string; a: string; b: string }[];
  verdict: string;
}

const COMPARISONS: Record<string, ComparisonPage> = {
  'louer-vs-acheter': {
    title: 'Louer ou acheter ?',
    intro:
      'Trois axes pour trancher : le coût total sur 10 ans, votre horizon de séjour, et votre tolérance au risque immobilier. Voici les chiffres typiques pour un appartement T3 à Douala / Yaoundé.',
    labelA: 'Location',
    labelB: 'Acquisition',
    rows: [
      { criterion: 'Mensualité moyenne T3', a: '250 000 FCFA', b: '650 000 FCFA (crédit 15 ans)' },
      { criterion: 'Apport initial', a: '2 mois de caution', b: '20–30 % du prix' },
      { criterion: 'Mobilité', a: 'Très flexible (préavis 3 mois)', b: 'Limitée (revente longue)' },
      { criterion: 'Frais annexes', a: 'Charges, agence', b: 'Notaire, taxes, entretien' },
      { criterion: 'Rentabilité', a: 'Aucune (loyer perdu)', b: 'Capital immobilier' },
      { criterion: 'Risque', a: 'Faible', b: 'Fluctuations marché, vacance' },
    ],
    verdict:
      'Acheter devient avantageux après ~7 ans de résidence stable. En dessous, la location reste financièrement plus saine.',
  },
  'douala-vs-yaounde': {
    title: 'Douala vs Yaoundé',
    intro:
      'Deux capitales, deux dynamiques. Douala concentre les sièges sociaux et le port ; Yaoundé l\'administration et les universités. Voici les écarts qui pèsent dans une décision d\'installation.',
    labelA: 'Douala',
    labelB: 'Yaoundé',
    rows: [
      { criterion: 'Prix T2 centre', a: '180–250k FCFA', b: '120–180k FCFA' },
      { criterion: 'Climat', a: 'Chaud + humide', b: 'Plus tempéré' },
      { criterion: 'Bassin d\'emploi', a: 'Industrie, commerce, finance', b: 'Administration, ONG' },
      { criterion: 'Trafic', a: 'Très dense', b: 'Modéré' },
      { criterion: 'Coût de la vie', a: '+15 % vs Yaoundé', b: 'Référence' },
      { criterion: 'Vie culturelle', a: 'Festivals, restaurants', b: 'Universités, théâtres' },
    ],
    verdict:
      'Choisissez Douala pour un secteur privé dynamique, Yaoundé pour un meilleur rapport qualité-de-vie / coût.',
  },
  'appartement-vs-maison': {
    title: 'Appartement vs Maison',
    intro:
      'L\'appartement maximise la centralité et minimise l\'entretien ; la maison offre l\'espace, le jardin, et l\'intimité — au prix de charges et de temps d\'entretien.',
    labelA: 'Appartement',
    labelB: 'Maison',
    rows: [
      { criterion: 'Surface moyenne', a: '60–90 m²', b: '120–200 m²' },
      { criterion: 'Charges communes', a: 'Oui (10–30k/mois)', b: 'Non' },
      { criterion: 'Intimité', a: 'Voisinage proche', b: 'Indépendance totale' },
      { criterion: 'Entretien', a: 'Léger', b: 'Important (jardin, toiture)' },
      { criterion: 'Centralité', a: 'Souvent en centre', b: 'Plutôt en périphérie' },
      { criterion: 'Plus-value', a: 'Stable', b: 'Plus volatile' },
    ],
    verdict:
      'Appartement pour un célibataire / jeune couple urbain. Maison dès qu\'il y a des enfants ou un projet de long terme.',
  },
};

/**
 * Fiche comparative — version mobile condensée de
 * `comparaison/[slug]` du web. Tableau scrollable + verdict + lien vers
 * la version complète sur le site.
 */
export default function ComparisonDetail() {
  const router = useRouter();
  const insets = useSafeAreaInsets();
  const { slug } = useLocalSearchParams<{ slug: string }>();
  const data = slug ? COMPARISONS[slug] : undefined;

  if (!data) {
    return (
      <YStack flex={1} alignItems="center" justifyContent="center" padding="$5" gap={10}>
        <Paragraph fontSize={15} fontWeight="700" color="$slate900">Comparatif introuvable</Paragraph>
        <Pressable onPress={() => router.back()} hitSlop={6}>
          <Paragraph color={brand.primary} fontWeight="700">Retour</Paragraph>
        </Pressable>
      </YStack>
    );
  }

  return (
    <>
      <Stack.Screen options={{ headerShown: false }} />
      <YStack flex={1} backgroundColor="$background">
        <XStack
          paddingTop={insets.top + 8}
          paddingHorizontal={14}
          paddingBottom={10}
          alignItems="center"
          gap={10}
          borderBottomWidth={1}
          borderBottomColor="$slate300"
        >
          <Pressable onPress={() => router.back()} hitSlop={8} accessibilityRole="button" accessibilityLabel="Retour">
            <YStack width={36} height={36} borderRadius={18} backgroundColor="$slate100" alignItems="center" justifyContent="center">
              <ArrowLeft size={18} color={brand.slate700} />
            </YStack>
          </Pressable>
          <H2 fontSize={18} fontWeight="700" color="$slate900" flex={1} numberOfLines={1}>
            {data.title}
          </H2>
        </XStack>

        <ScrollView
          contentContainerStyle={{ paddingHorizontal: 20, paddingTop: 18, paddingBottom: insets.bottom + 24, gap: 16 }}
        >
          <Paragraph fontSize={14} color="$slate700" lineHeight={22}>
            {data.intro}
          </Paragraph>

          <YStack borderRadius={14} borderWidth={1} borderColor="$slate300" overflow="hidden">
            <XStack backgroundColor="$slate100" paddingHorizontal={10} paddingVertical={10}>
              <Paragraph flex={1.4} fontSize={11.5} fontWeight="800" color="$slate500" textTransform="uppercase">Critère</Paragraph>
              <Paragraph flex={1} fontSize={11.5} fontWeight="800" color={brand.primary} textTransform="uppercase">{data.labelA}</Paragraph>
              <Paragraph flex={1} fontSize={11.5} fontWeight="800" color={brand.info} textTransform="uppercase">{data.labelB}</Paragraph>
            </XStack>
            {data.rows.map((row, idx) => (
              <XStack
                key={row.criterion}
                paddingHorizontal={10}
                paddingVertical={10}
                borderTopWidth={idx === 0 ? 0 : 1}
                borderTopColor="$slate100"
                alignItems="flex-start"
              >
                <Paragraph flex={1.4} fontSize={12} fontWeight="700" color="$slate900">
                  {row.criterion}
                </Paragraph>
                <Paragraph flex={1} fontSize={12} color="$slate700">
                  {row.a}
                </Paragraph>
                <Paragraph flex={1} fontSize={12} color="$slate700">
                  {row.b}
                </Paragraph>
              </XStack>
            ))}
          </YStack>

          <YStack padding={14} borderRadius={12} backgroundColor={`${brand.success}15`}>
            <Paragraph fontSize={12} fontWeight="800" color={brand.success} textTransform="uppercase">Verdict</Paragraph>
            <Paragraph fontSize={14} color="$slate900" lineHeight={20} marginTop={4}>
              {data.verdict}
            </Paragraph>
          </YStack>

          <Pressable onPress={() => Linking.openURL(`https://keyhome.app/comparaison/${slug}`)} hitSlop={6}>
            <XStack alignItems="center" justifyContent="center" gap={8} padding={14} borderRadius={12} borderWidth={1} borderColor="$slate300">
              <ExternalLink size={16} color={brand.slate700} />
              <Paragraph fontSize={14} fontWeight="700" color="$slate700">
                Lire l'article complet
              </Paragraph>
            </XStack>
          </Pressable>
        </ScrollView>
      </YStack>
    </>
  );
}
