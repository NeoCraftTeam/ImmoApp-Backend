import { ArrowLeft, ChevronRight, Scale } from '@tamagui/lucide-icons';
import { Stack, useRouter } from 'expo-router';
import { Pressable, ScrollView } from 'react-native';
import { H2, Paragraph, XStack, YStack } from 'tamagui';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

import { brand } from '@/theme/tokens';

const COMPARISONS = [
  {
    slug: 'louer-vs-acheter',
    title: 'Louer ou acheter en 2026 ?',
    summary:
      'Mensualités, rentabilité, mobilité, fiscalité — un guide complet pour trancher selon votre profil et votre horizon de séjour.',
    labels: ['Location', 'Acquisition'],
  },
  {
    slug: 'douala-vs-yaounde',
    title: 'Douala vs Yaoundé',
    summary:
      'Prix au m², proximité des bassins d\'emploi, qualité de vie, climat — où s\'installer entre les deux capitales économique et politique du Cameroun.',
    labels: ['Douala', 'Yaoundé'],
  },
  {
    slug: 'appartement-vs-maison',
    title: 'Appartement vs Maison',
    summary:
      'Surface utile, charges, intimité, valeur de revente — choisir le type de bien adapté à votre famille et à votre budget.',
    labels: ['Appartement', 'Maison'],
  },
];

/**
 * Index des comparatifs éditoriaux. Pages statiques côté web, on
 * reproduit la liste avec deep-link vers la fiche détaillée mobile
 * (`/comparaison/[slug]`).
 */
export default function ComparisonIndex() {
  const router = useRouter();
  const insets = useSafeAreaInsets();
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
          <H2 fontSize={20} fontWeight="700" color="$slate900" flex={1}>
            Comparatifs
          </H2>
        </XStack>

        <ScrollView
          contentContainerStyle={{ paddingHorizontal: 20, paddingTop: 18, paddingBottom: insets.bottom + 24, gap: 14 }}
        >
          <Paragraph fontSize={13} color="$slate500" lineHeight={20}>
            Des analyses comparatives pour vous aider à choisir le bien, la
            ville ou le mode d\'acquisition qui correspond à votre projet.
          </Paragraph>
          {COMPARISONS.map((c) => (
            <Pressable
              key={c.slug}
              onPress={() => router.push(`/comparaison/${c.slug}` as never)}
            >
              <YStack
                padding={14}
                borderRadius={14}
                borderWidth={1}
                borderColor="$slate300"
                gap={10}
                backgroundColor="$background"
              >
                <XStack alignItems="center" gap={10}>
                  <YStack width={40} height={40} borderRadius={20} backgroundColor={brand.primaryAlpha10} alignItems="center" justifyContent="center">
                    <Scale size={18} color={brand.primary} />
                  </YStack>
                  <YStack flex={1} gap={2}>
                    <Paragraph fontSize={15} fontWeight="700" color="$slate900" numberOfLines={2}>
                      {c.title}
                    </Paragraph>
                    <XStack gap={6} flexWrap="wrap">
                      {c.labels.map((l) => (
                        <XStack key={l} paddingHorizontal={8} paddingVertical={3} borderRadius={999} backgroundColor="$slate100">
                          <Paragraph fontSize={10} fontWeight="700" color="$slate700">{l}</Paragraph>
                        </XStack>
                      ))}
                    </XStack>
                  </YStack>
                  <ChevronRight size={16} color={brand.slate500} />
                </XStack>
                <Paragraph fontSize={13} color="$slate700" lineHeight={20}>
                  {c.summary}
                </Paragraph>
              </YStack>
            </Pressable>
          ))}
        </ScrollView>
      </YStack>
    </>
  );
}
