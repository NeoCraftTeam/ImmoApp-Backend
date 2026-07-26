import { ArrowLeft, ExternalLink } from '@tamagui/lucide-icons';
import { Stack, useRouter } from 'expo-router';
import { Linking, Pressable, ScrollView } from 'react-native';
import { H2, Paragraph, XStack, YStack } from 'tamagui';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

import { brand } from '@/theme/tokens';

const FULL_PRIVACY_URL = 'https://keyhome.app/confidentialite';

const SECTIONS = [
  {
    id: 'collecte',
    title: 'Données que nous collectons',
    body:
      'Profil (nom, email, téléphone, ville), contenu (annonces, messages, avis, favoris), données techniques (appareil, IP anonymisée), géolocalisation (uniquement avec votre accord pour la recherche par proximité).',
  },
  {
    id: 'utilisation',
    title: 'Utilisation des données',
    body:
      'Pour fournir le service (recherche, mise en relation, messagerie), prévenir la fraude, améliorer les recommandations, envoyer des notifications consenties, et respecter nos obligations légales.',
  },
  {
    id: 'partage',
    title: 'Partage des données',
    body:
      'Vos données ne sont jamais vendues. Elles peuvent être partagées avec des sous-traitants (hébergement, paiement) sous accord de confidentialité, ou sur réquisition légale. Les bailleurs voient uniquement vos coordonnées si vous les contactez.',
  },
  {
    id: 'securite',
    title: 'Sécurité',
    body:
      'Chiffrement TLS pour les transferts, hashing des mots de passe (bcrypt), tokens d\'API courte durée, isolation des données par tenant, audit régulier des accès. Aucun système n\'est infaillible ; signalez tout incident à security@keyhome.app.',
  },
  {
    id: 'cookies',
    title: 'Cookies et traceurs',
    body:
      'L\'application mobile n\'utilise pas de cookies tiers. Elle stocke localement votre session (SecureStore) et vos préférences. Les outils analytiques de Vercel agrègent des statistiques anonymes d\'usage.',
  },
  {
    id: 'droits',
    title: 'Vos droits',
    body:
      'Vous pouvez accéder, rectifier ou supprimer vos données depuis les paramètres. Pour exercer vos droits ou poser une question, écrivez à privacy@keyhome.app. Vous pouvez aussi déposer une réclamation auprès de l\'autorité de protection des données compétente.',
  },
  {
    id: 'enfants',
    title: 'Enfants',
    body:
      'KeyHome n\'est pas destiné aux personnes de moins de 16 ans. Aucune donnée n\'est collectée sciemment sur des mineurs. Tout compte identifié comme appartenant à un mineur sera supprimé.',
  },
  {
    id: 'transferts',
    title: 'Transferts internationaux',
    body:
      'Vos données sont hébergées en Europe et au Cameroun. Des transferts vers d\'autres juridictions ne se produisent que dans le cadre de prestataires offrant des garanties de protection équivalentes.',
  },
  {
    id: 'sondages',
    title: 'Sondages et études',
    body:
      'Nous pouvons vous proposer des sondages anonymes pour améliorer le service. Votre participation est facultative et révocable à tout moment.',
  },
  {
    id: 'conservation',
    title: 'Conservation',
    body:
      'Compte actif : tant que vous restez utilisateur. Compte supprimé : effacement sous 30 jours, sauf obligations légales (comptabilité, prévention de la fraude) jusqu\'à 5 ans.',
  },
  {
    id: 'modifications',
    title: 'Modifications de cette politique',
    body:
      'Toute évolution matérielle vous sera notifiée par email et dans l\'application. Vous restez libre de fermer votre compte si vous n\'acceptez pas les nouvelles règles.',
  },
];

/**
 * Mobile version of `/confidentialite`. Same shipping logic that
 * `Conditions` follows : a condensed, mobile-friendly summary of each
 * section, with a deep-link to the full canonical privacy document
 * on keyhome.app for the binding version.
 */
export default function Confidentialite() {
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
            Confidentialité
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
            KeyHome traite vos données avec respect et transparence. Voici un résumé ; la politique complète est sur le site web.
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
          <Pressable onPress={() => Linking.openURL(FULL_PRIVACY_URL)} hitSlop={6}>
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
                Lire la politique complète
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
