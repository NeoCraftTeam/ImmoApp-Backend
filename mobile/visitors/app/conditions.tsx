import { ArrowLeft, ExternalLink } from '@tamagui/lucide-icons';
import { Stack, useRouter } from 'expo-router';
import { Linking, Pressable, ScrollView } from 'react-native';
import { H2, Paragraph, XStack, YStack } from 'tamagui';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

import { brand } from '@/theme/tokens';

const FULL_TERMS_URL = 'https://keyhome.app/conditions';

const SECTIONS = [
  {
    id: 'description',
    title: 'Description du service',
    body:
      'KeyHome est une plateforme immobilière qui met en relation locataires/acquéreurs et bailleurs/propriétaires. Le service permet la recherche, la consultation, la mise en favoris, la comparaison et la prise de contact autour d\'annonces immobilières.',
  },
  {
    id: 'compte',
    title: 'Compte utilisateur',
    body:
      'L\'inscription est gratuite. Vous êtes responsable de la confidentialité de vos identifiants. Vos informations doivent être exactes et à jour. KeyHome peut suspendre tout compte enfreignant les présentes conditions.',
  },
  {
    id: 'publication',
    title: 'Règles de publication',
    body:
      'Les annonces doivent décrire un bien réel, disponible, et conforme à la législation locale. Les contenus discriminatoires, mensongers ou frauduleux sont strictement interdits et entraînent un retrait immédiat.',
  },
  {
    id: 'score-confiance',
    title: 'Score de Confiance',
    body:
      'Chaque utilisateur dispose d\'un score de confiance basé sur la complétude du profil, la vérification d\'identité, l\'historique d\'annonces et les avis reçus. Un score élevé débloque des fonctionnalités premium.',
  },
  {
    id: 'credits',
    title: 'Crédits et abonnements',
    body:
      'L\'accès aux contacts directs des bailleurs et au déverrouillage de certaines annonces premium nécessite des crédits ou un abonnement. Les crédits ne sont ni remboursables, ni cessibles, sauf en cas d\'annulation prévue par la loi.',
  },
  {
    id: 'ia',
    title: 'Outils assistés par l\'IA',
    body:
      'Certaines fonctionnalités (estimateur de loyer, recommandations, modération automatique) reposent sur des modèles d\'IA. Les résultats sont indicatifs et ne se substituent pas à un avis professionnel.',
  },
  {
    id: 'responsabilites',
    title: 'Responsabilités',
    body:
      'KeyHome agit comme intermédiaire. La conclusion d\'un bail, d\'une vente ou de toute transaction relève de la responsabilité exclusive des parties. KeyHome décline toute responsabilité quant à l\'exactitude des annonces tierces.',
  },
  {
    id: 'propriete',
    title: 'Propriété intellectuelle',
    body:
      'Le contenu publié sur KeyHome (logos, design, code, contenus éditoriaux) est protégé. Les contenus des utilisateurs restent leur propriété mais ils nous accordent une licence pour les diffuser sur la plateforme.',
  },
  {
    id: 'resiliation',
    title: 'Résiliation',
    body:
      'Vous pouvez supprimer votre compte à tout moment depuis les paramètres. KeyHome peut résilier un compte en cas de violation des présentes conditions, avec un préavis raisonnable lorsque cela est possible.',
  },
  {
    id: 'limitation',
    title: 'Limitation de responsabilité',
    body:
      'KeyHome ne saurait être tenu responsable des dommages indirects résultant de l\'usage du service. La responsabilité totale de KeyHome est limitée au montant des sommes versées au cours des 12 derniers mois.',
  },
  {
    id: 'modifications',
    title: 'Modifications et contact',
    body:
      'Ces conditions peuvent évoluer. Toute modification matérielle vous sera notifiée. Pour toute question : support@keyhome.app.',
  },
  {
    id: 'droit-applicable',
    title: 'Droit applicable et litiges',
    body:
      'Ces conditions sont soumises au droit camerounais. Tout litige sera porté devant les tribunaux compétents de Douala, sauf disposition impérative contraire.',
  },
];

/**
 * Mobile version of `/conditions` from the web. Apple/Google reviewers
 * expect a readable legal block inside the binary — we ship a condensed
 * version of each section and surface a link to the full canonical
 * document on the website for the binding text.
 */
export default function Conditions() {
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
            Conditions d'utilisation
          </H2>
        </XStack>

        <ScrollView
          contentContainerStyle={{
            paddingHorizontal: 20,
            paddingTop: 18,
            paddingBottom: insets.bottom + 24,
            gap: 18,
          }}
          showsVerticalScrollIndicator={false}
        >
          <Paragraph fontSize={13} color="$slate500" lineHeight={20}>
            Version résumée. Pour la version juridiquement contraignante,
            consultez le document complet sur le site web.
          </Paragraph>
          {SECTIONS.map((section) => (
            <YStack key={section.id} gap={6}>
              <Paragraph fontSize={15} fontWeight="700" color="$slate900">
                {section.title}
              </Paragraph>
              <Paragraph fontSize={14} color="$slate700" lineHeight={22}>
                {section.body}
              </Paragraph>
            </YStack>
          ))}
          <Pressable onPress={() => Linking.openURL(FULL_TERMS_URL)} hitSlop={6}>
            <XStack
              alignItems="center"
              justifyContent="center"
              gap={8}
              padding={14}
              borderRadius={12}
              backgroundColor={brand.slate900}
              marginTop={6}
            >
              <ExternalLink size={16} color="white" />
              <Paragraph fontSize={14} fontWeight="700" color="white">
                Lire la version complète
              </Paragraph>
            </XStack>
          </Pressable>
          <Paragraph fontSize={11} color="$slate500" textAlign="center">
            Dernière mise à jour : 1ᵉʳ janvier 2026
          </Paragraph>
        </ScrollView>
      </YStack>
    </>
  );
}
