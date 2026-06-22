import { ArrowLeft, ChevronDown, ChevronUp, ExternalLink, HelpCircle, Mail } from '@tamagui/lucide-icons';
import { Stack, useRouter } from 'expo-router';
import { useState } from 'react';
import { Linking, Pressable, ScrollView } from 'react-native';
import { H2, Paragraph, XStack, YStack } from 'tamagui';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

import { brand } from '@/theme/tokens';

const FAQ: { q: string; a: string }[] = [
  {
    q: 'Comment contacter un bailleur ?',
    a: 'Ouvrez la fiche détail d\'une annonce et touchez "Contacter". Si le bailleur a renseigné un numéro WhatsApp, vous ouvrirez directement la conversation; sinon vous serez redirigé vers la messagerie KeyHome.',
  },
  {
    q: 'À quoi servent les crédits KeyHome ?',
    a: 'Les crédits servent à débloquer le contact direct des bailleurs et l\'accès complet aux photos sur certaines annonces premium. Rechargez vos crédits depuis l\'onglet "Crédits" du compte.',
  },
  {
    q: 'Comment fonctionnent les alertes de recherche ?',
    a: 'Définissez vos critères (ville, type, prix, etc.) et choisissez une fréquence. KeyHome vous enverra une notification dès qu\'une nouvelle annonce correspondante est publiée.',
  },
  {
    q: 'Qu\'est-ce que le KeyScore ?',
    a: 'Le KeyScore est une note sur 100 qui évalue la complétude d\'une annonce : photos, description, prix, équipements et localisation. Plus le score est élevé, plus l\'annonce est fiable.',
  },
  {
    q: 'Comment supprimer mon compte ?',
    a: 'Allez dans Paramètres → Supprimer le compte, ou écrivez-nous à support@keyhome.app. Vos favoris et messages seront effacés de façon irréversible.',
  },
  {
    q: 'Comment fonctionne la note de quartier ?',
    a: 'La note de quartier agrège la proximité des transports, commerces, services de santé, écoles et lieux de loisirs. Les données viennent d\'OpenStreetMap et sont mises à jour quotidiennement.',
  },
];

export default function Aide() {
  const router = useRouter();
  const insets = useSafeAreaInsets();
  const [open, setOpen] = useState<number | null>(0);

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
            <YStack
              width={36}
              height={36}
              borderRadius={18}
              backgroundColor="$slate100"
              alignItems="center"
              justifyContent="center"
            >
              <ArrowLeft size={18} color={brand.slate700} />
            </YStack>
          </Pressable>
          <H2 fontSize={20} fontWeight="700" color="$slate900" flex={1}>
            Aide
          </H2>
        </XStack>

        <ScrollView
          contentContainerStyle={{
            paddingHorizontal: 16,
            paddingTop: 18,
            paddingBottom: insets.bottom + 24,
            gap: 12,
          }}
          showsVerticalScrollIndicator={false}
        >
          <YStack
            padding={16}
            borderRadius={14}
            backgroundColor="$slate100"
            gap={10}
          >
            <XStack alignItems="center" gap={8}>
              <HelpCircle size={20} color={brand.primary} />
              <Paragraph fontSize={15} fontWeight="700" color="$slate900">
                Une question ?
              </Paragraph>
            </XStack>
            <Paragraph fontSize={13} color="$slate700" lineHeight={20}>
              Parcourez la FAQ ci-dessous ou contactez l'équipe support
              KeyHome. Nous répondons sous 24 h en jours ouvrés.
            </Paragraph>
            <Pressable
              onPress={() => Linking.openURL('mailto:support@keyhome.app')}
              hitSlop={6}
            >
              <XStack
                backgroundColor="$brand"
                paddingHorizontal={14}
                paddingVertical={10}
                borderRadius={10}
                alignItems="center"
                gap={8}
                alignSelf="flex-start"
              >
                <Mail size={15} color="white" />
                <Paragraph color="white" fontWeight="700">
                  Écrire au support
                </Paragraph>
              </XStack>
            </Pressable>
          </YStack>

          <Paragraph
            fontSize={11}
            fontWeight="800"
            color="$slate500"
            textTransform="uppercase"
            marginTop={4}
          >
            Foire aux questions
          </Paragraph>

          {FAQ.map((item, idx) => (
            <FaqItem
              key={idx}
              question={item.q}
              answer={item.a}
              isOpen={open === idx}
              onToggle={() => setOpen(open === idx ? null : idx)}
            />
          ))}

          <Pressable
            onPress={() => Linking.openURL('https://keyhome.app/aide')}
            hitSlop={6}
          >
            <XStack
              alignItems="center"
              justifyContent="center"
              gap={8}
              padding={14}
              borderRadius={12}
              borderWidth={1}
              borderColor="$slate300"
              marginTop={6}
            >
              <ExternalLink size={16} color={brand.slate700} />
              <Paragraph fontSize={14} fontWeight="700" color="$slate700">
                Centre d'aide complet
              </Paragraph>
            </XStack>
          </Pressable>
        </ScrollView>
      </YStack>
    </>
  );
}

function FaqItem({
  question,
  answer,
  isOpen,
  onToggle,
}: {
  question: string;
  answer: string;
  isOpen: boolean;
  onToggle: () => void;
}) {
  return (
    <Pressable onPress={onToggle}>
      <YStack
        borderRadius={12}
        borderWidth={1}
        borderColor="$slate300"
        backgroundColor="$background"
        padding={14}
        gap={isOpen ? 8 : 0}
      >
        <XStack alignItems="center" gap={10}>
          <Paragraph fontSize={14.5} fontWeight="700" color="$slate900" flex={1}>
            {question}
          </Paragraph>
          {isOpen ? (
            <ChevronUp size={16} color={brand.slate700} />
          ) : (
            <ChevronDown size={16} color={brand.slate700} />
          )}
        </XStack>
        {isOpen && (
          <Paragraph fontSize={13} color="$slate700" lineHeight={20}>
            {answer}
          </Paragraph>
        )}
      </YStack>
    </Pressable>
  );
}
