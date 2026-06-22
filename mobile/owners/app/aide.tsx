import { ChevronDown, ChevronUp, Mail, MessageCircle } from '@tamagui/lucide-icons';
import { useState } from 'react';
import { Linking, Pressable, ScrollView } from 'react-native';
import { Button, H1, Paragraph, XStack, YStack } from 'tamagui';

import { ScreenHeader } from '@/components/ScreenHeader';
import { brand } from '@/theme/tokens';

const SUPPORT_EMAIL = 'support@keyhome.app';
const SUPPORT_WHATSAPP = '237600000000';

const FAQ: { question: string; answer: string }[] = [
  {
    question: 'Comment publier une annonce ?',
    answer:
      'Depuis l’onglet « Annonces », appuyez sur « Nouvelle annonce » et suivez les étapes : informations, localisation, caractéristiques, charges et photos. Une fois complétée, publiez l’annonce — elle sera soumise à validation avant d’être visible.',
  },
  {
    question: 'Comment booster une annonce ?',
    answer:
      'Ouvrez l’annonce concernée, choisissez l’action « Booster », puis sélectionnez un pack de boost. Le boost place votre annonce en tête des résultats pendant une durée définie et consomme des crédits depuis votre solde.',
  },
  {
    question: 'Comment imprimer une pancarte ?',
    answer:
      'Sur une annonce, utilisez l’action « Pancarte ». Une affiche avec le QR code de votre annonce est générée : vous pouvez la télécharger en PDF, l’imprimer ou la partager. Les prospects scannent le QR code pour ouvrir l’annonce en ligne.',
  },
  {
    question: 'Comment gérer les demandes de visite ?',
    answer:
      'L’onglet « Visites » regroupe toutes les demandes de vos prospects. Vous pouvez confirmer une visite, appeler le prospect, ou la marquer comme « Absent » si la personne ne s’est pas présentée.',
  },
  {
    question: 'Comment changer mon abonnement ?',
    answer:
      'Rendez-vous dans « Plus » puis « Abonnement ». Vous y voyez votre formule actuelle et pouvez choisir une nouvelle formule (mensuelle ou annuelle). Les formules supérieures augmentent votre nombre d’annonces et votre score de boost.',
  },
  {
    question: 'Comment ajouter un locataire ou un bail ?',
    answer:
      'Dans « Plus », ouvrez « Locataires » pour enregistrer vos locataires, puis « Baux » pour consulter vos contrats. Les locataires enregistrés peuvent ensuite être associés à vos contrats de bail.',
  },
];

export default function AideScreen() {
  const [openIndex, setOpenIndex] = useState<number | null>(0);

  const toggle = (index: number) => {
    setOpenIndex((current) => (current === index ? null : index));
  };

  return (
    <YStack flex={1} backgroundColor="$background">
      <ScreenHeader title="Aide & support" />

      <ScrollView contentContainerStyle={{ paddingHorizontal: 16, paddingTop: 16, paddingBottom: 32 }} showsVerticalScrollIndicator={false}>
        <H1 fontSize={18} fontWeight="800" color="$slate900" marginBottom={12}>
          Questions fréquentes
        </H1>

        <YStack gap={10}>
          {FAQ.map((item, index) => {
            const isOpen = openIndex === index;
            return (
              <YStack
                key={item.question}
                borderWidth={1}
                borderColor="$slate300"
                borderRadius={16}
                overflow="hidden"
                backgroundColor="$background"
              >
                <Pressable onPress={() => toggle(index)} accessibilityLabel={item.question}>
                  <XStack alignItems="center" gap={10} paddingHorizontal={16} paddingVertical={14}>
                    <Paragraph fontSize={14.5} fontWeight="700" color="$slate900" flex={1}>
                      {item.question}
                    </Paragraph>
                    {isOpen ? (
                      <ChevronUp size={18} color={brand.slate500} />
                    ) : (
                      <ChevronDown size={18} color={brand.slate500} />
                    )}
                  </XStack>
                </Pressable>
                {isOpen ? (
                  <YStack paddingHorizontal={16} paddingBottom={14}>
                    <Paragraph fontSize={13.5} color="$slate700" lineHeight={20}>
                      {item.answer}
                    </Paragraph>
                  </YStack>
                ) : null}
              </YStack>
            );
          })}
        </YStack>

        <YStack
          marginTop={24}
          padding={18}
          borderRadius={18}
          backgroundColor={brand.primaryAlpha10}
          gap={12}
        >
          <YStack gap={4}>
            <H1 fontSize={17} fontWeight="800" color="$slate900">
              Nous contacter
            </H1>
            <Paragraph fontSize={13} color="$slate700" lineHeight={19}>
              Une question, un problème ? Notre équipe support vous répond rapidement.
            </Paragraph>
          </YStack>

          <Button
            size="$4"
            backgroundColor="$brand"
            color="white"
            fontWeight="800"
            borderRadius={12}
            icon={<Mail size={17} color="white" />}
            onPress={() => Linking.openURL(`mailto:${SUPPORT_EMAIL}`)}
          >
            Écrire par email
          </Button>

          <Button
            size="$4"
            chromeless
            borderWidth={1}
            borderColor="$slate300"
            borderRadius={12}
            icon={<MessageCircle size={17} color={brand.success} />}
            onPress={() => Linking.openURL(`https://wa.me/${SUPPORT_WHATSAPP}`)}
          >
            <Paragraph fontSize={15} fontWeight="700" color="$slate900">
              WhatsApp
            </Paragraph>
          </Button>

          <Paragraph fontSize={12} color="$slate500" textAlign="center">
            {SUPPORT_EMAIL}
          </Paragraph>
        </YStack>
      </ScrollView>
    </YStack>
  );
}
