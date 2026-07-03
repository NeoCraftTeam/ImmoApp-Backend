import { ArrowLeft, BookOpen, ChevronRight } from '@tamagui/lucide-icons';
import { Stack, useRouter } from 'expo-router';
import { Pressable, ScrollView } from 'react-native';
import { H2, Paragraph, XStack, YStack } from 'tamagui';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

import { brand } from '@/theme/tokens';

const POSTS = [
  {
    slug: 'guide-premiere-location-cameroun',
    title: 'Guide complet : louer son premier logement au Cameroun',
    excerpt:
      'Caution, état des lieux, charges, documents requis — toutes les étapes pour signer son premier bail sans mauvaise surprise.',
    minutes: 8,
    category: 'Guides',
  },
  {
    slug: 'erreurs-eviter-visiter-bien',
    title: '7 erreurs à éviter avant de visiter un bien',
    excerpt:
      'Vérifications obligatoires, questions à poser au bailleur, signaux d\'alerte. Comment ne pas se faire piéger lors d\'une visite.',
    minutes: 5,
    category: 'Conseils',
  },
  {
    slug: 'comprendre-keyscore',
    title: 'Comprendre le KeyScore d\'une annonce',
    excerpt:
      'Comment KeyHome évalue la qualité d\'une annonce et pourquoi un score élevé augmente vos chances de tomber sur un bon bien.',
    minutes: 4,
    category: 'Plateforme',
  },
  {
    slug: 'negocier-loyer',
    title: 'Négocier son loyer : les techniques qui fonctionnent',
    excerpt:
      'Arguments à utiliser, moment idéal, comparaison du marché. 10 conseils concrets pour obtenir une réduction de 5 à 15 %.',
    minutes: 6,
    category: 'Conseils',
  },
];

export default function BlogIndex() {
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
              <ArrowLeft size={18} color="$slate700" />
            </YStack>
          </Pressable>
          <H2 fontSize={20} fontWeight="700" color="$slate900" flex={1}>
            Le Blog KeyHome
          </H2>
        </XStack>

        <ScrollView
          contentContainerStyle={{ paddingHorizontal: 20, paddingTop: 18, paddingBottom: insets.bottom + 24, gap: 14 }}
        >
          <Paragraph fontSize={13} color="$slate500" lineHeight={20}>
            Guides, conseils et analyses pour mieux louer, mieux acheter et mieux investir.
          </Paragraph>
          {POSTS.map((p) => (
            <Pressable key={p.slug} onPress={() => router.push(`/blog/${p.slug}` as never)}>
              <YStack padding={14} borderRadius={14} borderWidth={1} borderColor="$slate300" gap={8} backgroundColor="$background">
                <XStack alignItems="center" gap={6}>
                  <YStack width={32} height={32} borderRadius={16} backgroundColor={brand.primaryAlpha10} alignItems="center" justifyContent="center">
                    <BookOpen size={15} color={brand.primary} />
                  </YStack>
                  <Paragraph fontSize={11} fontWeight="800" color={brand.primary} textTransform="uppercase">
                    {p.category}
                  </Paragraph>
                  <Paragraph fontSize={11} color="$slate500">· {p.minutes} min</Paragraph>
                </XStack>
                <Paragraph fontSize={15} fontWeight="700" color="$slate900">
                  {p.title}
                </Paragraph>
                <Paragraph fontSize={13} color="$slate700" lineHeight={20}>
                  {p.excerpt}
                </Paragraph>
                <XStack alignItems="center" gap={4} marginTop={4}>
                  <Paragraph fontSize={12} fontWeight="700" color={brand.primary}>Lire l'article</Paragraph>
                  <ChevronRight size={14} color={brand.primary} />
                </XStack>
              </YStack>
            </Pressable>
          ))}
        </ScrollView>
      </YStack>
    </>
  );
}
