import { ArrowLeft, ExternalLink } from '@tamagui/lucide-icons';
import { Stack, useLocalSearchParams, useRouter } from 'expo-router';
import { Linking, Pressable, ScrollView } from 'react-native';
import { Paragraph, XStack, YStack } from 'tamagui';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

import { brand } from '@/theme/tokens';

interface BlogPost {
  title: string;
  category: string;
  minutes: number;
  intro: string;
  body: { heading: string; paragraphs: string[] }[];
}

const POSTS: Record<string, BlogPost> = {
  'guide-premiere-location-cameroun': {
    title: 'Guide complet : louer son premier logement au Cameroun',
    category: 'Guides',
    minutes: 8,
    intro:
      'Louer pour la première fois implique de naviguer entre exigences administratives, négociations et vérifications. Ce guide concentre l\'essentiel pour signer un bail sans mauvaise surprise.',
    body: [
      {
        heading: 'Les documents à préparer',
        paragraphs: [
          'CNI valide, justificatif de revenus (bulletins de salaire des 3 derniers mois ou attestation de stage), garant éventuel et photocopie de sa CNI. Si vous travaillez à votre compte, ajoutez votre attestation de patente.',
          'Le bailleur exigera souvent la caution (2 mois) et 1 mois d\'avance, soit 3 mois au signing. Anticipez ce cash flow.',
        ],
      },
      {
        heading: 'L\'état des lieux',
        paragraphs: [
          'Photographiez chaque pièce, robinet, prise et fissure le jour de l\'entrée. Ce document signé vous protégera lors de la sortie.',
          'Vérifiez le compteur eau/électricité — relevez les chiffres en présence du bailleur.',
        ],
      },
      {
        heading: 'Les charges',
        paragraphs: [
          'Demandez si l\'eau et l\'électricité sont incluses ou séparées. Pour les copropriétés, vérifiez les charges communes (gardiennage, ascenseur, ordures).',
        ],
      },
      {
        heading: 'Signaux d\'alerte',
        paragraphs: [
          'Bailleur qui refuse un état des lieux ou la signature d\'un contrat écrit : fuyez. Sous-location déguisée (le bailleur n\'est pas le propriétaire) : exigez le titre foncier.',
        ],
      },
    ],
  },
  'erreurs-eviter-visiter-bien': {
    title: '7 erreurs à éviter avant de visiter un bien',
    category: 'Conseils',
    minutes: 5,
    intro:
      'Une visite n\'est pas qu\'une formalité — c\'est votre meilleur outil de filtrage. Voici les 7 pièges qui font perdre temps et argent.',
    body: [
      {
        heading: '1. Visiter seul',
        paragraphs: [
          'Toujours venir à deux : un voit ce que l\'autre rate. Discutez ensuite à froid des points soulevés.',
        ],
      },
      {
        heading: '2. Visiter en plein jour',
        paragraphs: [
          'Demandez aussi une visite en fin de journée pour évaluer la luminosité, le bruit du voisinage et la sécurité du quartier après la tombée de la nuit.',
        ],
      },
      {
        heading: '3. Oublier de tester les robinets',
        paragraphs: [
          'Ouvrez chaque robinet pendant 30 secondes, observez la pression, la couleur et l\'odeur de l\'eau. Pareil pour la chasse d\'eau.',
        ],
      },
      {
        heading: '4. Ignorer le voisinage',
        paragraphs: [
          'Parlez aux voisins. Cinq minutes de discussion sur le palier révèlent souvent ce que le bailleur cache : mur fragile, conflit, problèmes d\'eau.',
        ],
      },
      {
        heading: '5. Ne pas vérifier le réseau mobile',
        paragraphs: [
          'Faites un appel test à chaque endroit du logement. Un quartier avec 1 barre = télétravail impossible.',
        ],
      },
      {
        heading: '6. Sauter la lecture du contrat',
        paragraphs: [
          'Lisez chaque clause avant de signer. Particulièrement : durée du bail, conditions de résiliation, indexation du loyer.',
        ],
      },
      {
        heading: '7. Verser une avance avant signature',
        paragraphs: [
          'Aucune somme ne doit changer de mains avant le contrat signé et l\'état des lieux fait. C\'est la règle d\'or.',
        ],
      },
    ],
  },
  'comprendre-keyscore': {
    title: 'Comprendre le KeyScore d\'une annonce',
    category: 'Plateforme',
    minutes: 4,
    intro:
      'Le KeyScore (0–100) est notre indicateur de qualité d\'annonce. Plus il est élevé, plus l\'annonce est fiable, complète et susceptible de correspondre à votre projet.',
    body: [
      {
        heading: 'Comment il est calculé',
        paragraphs: [
          'Cinq critères pondérés : qualité des photos (25 %), complétude de la description (20 %), pertinence du prix vs marché (20 %), équipements renseignés (20 %), fraîcheur de l\'annonce (15 %).',
        ],
      },
      {
        heading: 'Comment l\'utiliser',
        paragraphs: [
          'En dessous de 50 : prudence, l\'annonce manque d\'éléments clés. Entre 50 et 75 : correcte, à vérifier en visite. Au-dessus de 75 : annonce premium, photos pro, description fournie.',
        ],
      },
      {
        heading: 'Pour les bailleurs',
        paragraphs: [
          'Améliorer son KeyScore = plus de visibilité, plus de contacts et un loyer obtenu plus rapidement. Ajoutez des photos pro, détaillez les équipements et fixez un prix de marché.',
        ],
      },
    ],
  },
  'negocier-loyer': {
    title: 'Négocier son loyer : les techniques qui fonctionnent',
    category: 'Conseils',
    minutes: 6,
    intro:
      'La négociation locative est sous-utilisée. Bien menée, elle fait économiser 5 à 15 % du loyer initial. Voici les arguments qui font mouche.',
    body: [
      {
        heading: 'Comparer le marché',
        paragraphs: [
          'Ouvrez 5 annonces similaires dans le même quartier, capturez les prix et présentez-les au bailleur. "Voilà ce que je trouve au m² ailleurs."',
        ],
      },
      {
        heading: 'Mettre en avant votre profil',
        paragraphs: [
          'Salarié stable, garant solide, dossier complet — autant de signaux qui rassurent. Un bailleur préfère un bon dossier à 10 % de moins qu\'un dossier risqué au prix fort.',
        ],
      },
      {
        heading: 'Engager sur la durée',
        paragraphs: [
          'Proposez un bail plus long en échange d\'une remise. Le bailleur évite la vacance et la charge d\'un nouveau locataire à trouver.',
        ],
      },
      {
        heading: 'Payer plusieurs mois d\'avance',
        paragraphs: [
          'Si vous avez la trésorerie, payer 3 mois d\'avance vs 1 peut justifier 5 à 10 % de remise. À calculer selon votre coût d\'opportunité.',
        ],
      },
      {
        heading: 'Quand négocier',
        paragraphs: [
          'En fin de mois (le bailleur veut louer avant que le mois recommence) ou si l\'annonce date de plus de 3 semaines (vacance qui coûte).',
        ],
      },
    ],
  },
};

export default function BlogPost() {
  const router = useRouter();
  const insets = useSafeAreaInsets();
  const { slug } = useLocalSearchParams<{ slug: string }>();
  const post = slug ? POSTS[slug] : undefined;

  if (!post) {
    return (
      <YStack flex={1} alignItems="center" justifyContent="center" padding="$5" gap={10}>
        <Paragraph fontSize={15} fontWeight="700" color="$slate900">Article introuvable</Paragraph>
        <Pressable onPress={() => router.back()} hitSlop={6}>
          <Paragraph color={brand.primary} fontWeight="700">Retour au blog</Paragraph>
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
          <Paragraph fontSize={13} color="$slate500" flex={1} numberOfLines={1}>
            {post.category} · {post.minutes} min
          </Paragraph>
        </XStack>

        <ScrollView
          contentContainerStyle={{ paddingHorizontal: 20, paddingTop: 18, paddingBottom: insets.bottom + 24, gap: 18 }}
        >
          <Paragraph fontSize={11} fontWeight="800" color={brand.primary} textTransform="uppercase">
            {post.category}
          </Paragraph>
          <Paragraph fontSize={24} fontWeight="800" color="$slate900" lineHeight={30}>
            {post.title}
          </Paragraph>
          <Paragraph fontSize={15} color="$slate700" lineHeight={24} fontStyle="italic">
            {post.intro}
          </Paragraph>

          {post.body.map((section) => (
            <YStack key={section.heading} gap={8}>
              <Paragraph fontSize={17} fontWeight="700" color="$slate900">
                {section.heading}
              </Paragraph>
              {section.paragraphs.map((para, i) => (
                <Paragraph key={i} fontSize={14.5} color="$slate700" lineHeight={24}>
                  {para}
                </Paragraph>
              ))}
            </YStack>
          ))}

          <Pressable onPress={() => Linking.openURL(`https://keyhome.app/blog/${slug}`)} hitSlop={6}>
            <XStack alignItems="center" justifyContent="center" gap={8} padding={14} borderRadius={12} borderWidth={1} borderColor="$slate300" marginTop={6}>
              <ExternalLink size={16} color={brand.slate700} />
              <Paragraph fontSize={14} fontWeight="700" color="$slate700">
                Lire la version web
              </Paragraph>
            </XStack>
          </Pressable>
        </ScrollView>
      </YStack>
    </>
  );
}
