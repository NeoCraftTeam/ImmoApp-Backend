import {
  ArrowLeft,
  Bed,
  CalendarCheck,
  CalendarPlus,
  CheckCircle2,
  Eye,
  MapPin,
  Maximize,
  MessageCircle,
  ParkingCircle,
  Phone,
  Share2,
  ShieldCheck,
  ShowerHead,
  Wallet,
  X,
} from '@tamagui/lucide-icons';
import { formatDistanceToNow } from 'date-fns';
import { fr } from 'date-fns/locale';
import { Stack, useLocalSearchParams, useRouter } from 'expo-router';
import { Image } from 'expo-image';
import { useEffect, useMemo, useRef, useState } from 'react';
import {
  Alert,
  Animated,
  FlatList,
  Linking,
  Modal,
  Pressable,
  RefreshControl,
  Share,
  useWindowDimensions,
} from 'react-native';
import { Button, H2, Paragraph, ScrollView, XStack, YStack } from 'tamagui';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

import { extractApiErrorMessage, getApiErrorStatus } from '@/api/client';
import { CompareButton } from '@/components/CompareButton';
import { FavoriteButton } from '@/components/FavoriteButton';
import { AdDetailSkeleton } from '@/components/ads/AdDetailSkeleton';
import { BookViewingSheet } from '@/components/ads/BookViewingSheet';
import { ImageLightbox } from '@/components/ads/ImageLightbox';
import { KeyScoreSection } from '@/components/ads/KeyScoreSection';
import { useKeyScore } from '@/hooks/useKeyScore';
import { useRecordRecentlyViewed, useRemoveRecentlyViewed } from '@/hooks/useRecentlyViewed';
import { LocationMap } from '@/components/ads/LocationMap';
import { NeighborhoodScorecard } from '@/components/ads/NeighborhoodScorecard';
import { PropertyAttributes } from '@/components/ads/PropertyAttributes';
import { SupplementaryInfoCard } from '@/components/ads/SupplementaryInfoCard';
import { VirtualTourSection } from '@/components/ads/VirtualTourSection';
import { hasSupplementaryInfo } from '@/utils/supplementary-info';
import { ReviewsSection } from '@/components/ads/ReviewsSection';
import { SearchAlertButton } from '@/components/ads/SearchAlertButton';
import { SimilarAdsCarousel } from '@/components/ads/SimilarAdsCarousel';
import { useAd } from '@/hooks/useAd';
import { useCurrency } from '@/hooks/useCurrency';
import { useReducedMotion } from '@/hooks/useReducedMotion';
import { useStartConversation } from '@/hooks/useConversations';
import { useUnlockAd } from '@/hooks/useCredits';
import { useCreditsBalance } from '@/hooks/usePayments';
import { useSession } from '@/auth/SessionProvider';
import { resolveMediaUrl } from '@/lib/media-url';
import { useThemeColors } from '@/theme/useThemeColors';
import { brand } from '@/theme/tokens';
import { t } from '@/i18n';
import type { Ad, AdImage } from '@/types/ad';

const HERO_RATIO = 0.62;
const OVERLAP = 28;
const DESCRIPTION_CLAMP = 6;

/**
 * Ad-detail screen — Airbnb-inspired, mirroring `AdDetailClient` from
 * the Next.js web app. The hero image carousel is full-bleed at the
 * top, the white sheet of content overlaps it by 28 px with rounded
 * top corners, and a sticky CTA pinned to the bottom provides the
 * primary conversion action.
 *
 * Parallax + chrome fade-in use React Native's built-in `Animated`
 * with `useNativeDriver: true`, so the scroll-driven transforms run
 * on the UI thread without the Reanimated worklets bridge (which is
 * fragile inside Expo Go).
 */
export default function AdDetail() {
  const { slug } = useLocalSearchParams<{ slug: string }>();
  const insets = useSafeAreaInsets();
  const router = useRouter();
  const { isAuthenticated } = useSession();
  const { width, height } = useWindowDimensions();

  const heroHeight = Math.round(height * HERO_RATIO);

  const { data: ad, isLoading, isError, error, refetch, isRefetching } = useAd(slug);
  const startConversation = useStartConversation();
  const unlockAd = useUnlockAd();

  // Historise l'annonce consultée pour le carrousel « Récemment consultés »
  // de l'accueil (persisté en AsyncStorage, dédupliqué, max 10).
  const recordView = useRecordRecentlyViewed();
  useEffect(() => {
    if (ad?.id) {
      void recordView(ad);
    }
  }, [ad, recordView]);

  // Annonce introuvable (supprimée, ou d'un autre environnement resté en
  // cache) → on la retire du carrousel « Récemment consultés ».
  const removeRecent = useRemoveRecentlyViewed();
  useEffect(() => {
    if (isError && slug) {
      void removeRecent(slug);
    }
  }, [isError, slug, removeRecent]);

  const [activeImage, setActiveImage] = useState(0);
  const [descOpen, setDescOpen] = useState(false);
  const [bookingOpen, setBookingOpen] = useState(false);
  const [lightboxOpen, setLightboxOpen] = useState(false);

  const carouselRef = useRef<FlatList<AdImage> | null>(null);
  const scrollY = useRef(new Animated.Value(0)).current;
  const reducedMotion = useReducedMotion();

  // Parallax — hero translates up at 40 % of scroll speed.
  // Reduced motion (HIG) : pas d'effet de profondeur — le hero suit le
  // scroll en 1:1, comme une image ordinaire dans le flux.
  const heroTranslateY = scrollY.interpolate({
    inputRange: [-heroHeight, 0, heroHeight],
    outputRange: [0, 0, reducedMotion ? -heroHeight : -heroHeight * 0.4],
    extrapolate: 'clamp',
  });
  // Overscroll zoom — pull-to-refresh feel ; désactivé sous reduced motion.
  const heroScale = scrollY.interpolate({
    inputRange: [-heroHeight, 0, 1],
    outputRange: [reducedMotion ? 1 : 1.4, 1, 1],
    extrapolate: 'clamp',
  });
  // Top chrome background fades in as user scrolls past the hero
  const chromeBgOpacity = scrollY.interpolate({
    inputRange: [heroHeight - OVERLAP - 60, heroHeight - OVERLAP],
    outputRange: [0, 1],
    extrapolate: 'clamp',
  });

  if (isLoading) {
    return (
      <YStack flex={1} backgroundColor="$background">
        <Stack.Screen options={{ headerShown: false }} />
        <ScrollView contentContainerStyle={{ paddingTop: insets.top }}>
          <AdDetailSkeleton />
        </ScrollView>
      </YStack>
    );
  }

  if (isError || !ad) {
    return (
      <YStack
        flex={1}
        backgroundColor="$background"
        justifyContent="center"
        alignItems="center"
        padding="$5"
        gap="$3"
      >
        <Paragraph color="$slate700" textAlign="center">
          {extractApiErrorMessage(error)}
        </Paragraph>
        <Button onPress={() => router.back()} size="$3">
          {t('common.back')}
        </Button>
      </YStack>
    );
  }

  const handleShare = async () => {
    try {
      const url =
        (ad as unknown as { canonical_url?: string }).canonical_url ??
        `https://app.keyhome.app/ads/${ad.slug ?? ad.id}`;
      await Share.share({ url, message: `${ad.title}\n${url}` });
    } catch {
      /* user cancelled */
    }
  };

  const unlockCost = ad.unlock_cost ?? 2;
  const creditsLabel = `${unlockCost} crédit${unlockCost > 1 ? 's' : ''}`;

  const promptBuyCredits = () => {
    Alert.alert(
      'Crédits insuffisants',
      `Il vous faut ${creditsLabel} pour débloquer cette annonce. Rechargez votre solde pour continuer.`,
      [
        { text: 'Plus tard', style: 'cancel' },
        { text: 'Acheter des crédits', onPress: () => router.push('/credits') },
      ],
    );
  };

  /**
   * Déverrouille le contact de l'annonce (adresse exacte, téléphone,
   * messagerie) en dépensant des crédits. `onUnlocked` est appelé après
   * succès — utilisé par le CTA Message pour enchaîner sur la conversation.
   */
  const handleUnlock = (onUnlocked?: () => void) => {
    if (!isAuthenticated) {
      router.push('/(auth)/login');
      return;
    }
    if (unlockAd.isPending) {
      return;
    }
    Alert.alert(
      'Débloquer cette annonce',
      `Le déverrouillage utilise ${creditsLabel} et affiche l'adresse exacte, le téléphone de l'annonceur et la messagerie.`,
      [
        { text: 'Annuler', style: 'cancel' },
        {
          text: `Débloquer (${creditsLabel})`,
          onPress: () => {
            unlockAd.mutate(
              { adId: ad.id, slugOrId: slug ?? ad.id },
              {
                onSuccess: (res) => {
                  if (res.status === 'unlocked' || res.status === 'already_unlocked') {
                    if (onUnlocked) {
                      onUnlocked();
                    } else {
                      Alert.alert(
                        'Annonce débloquée',
                        'Vous pouvez maintenant contacter l’annonceur et voir l’adresse exacte.',
                      );
                    }
                  } else if (res.status === 'owner') {
                    Alert.alert('Information', 'Vous êtes l’annonceur de cette annonce.');
                  } else {
                    promptBuyCredits();
                  }
                },
                onError: (err) => {
                  if (getApiErrorStatus(err) === 402) {
                    promptBuyCredits();
                    return;
                  }
                  Alert.alert('Erreur', extractApiErrorMessage(err));
                },
              },
            );
          },
        },
      ],
    );
  };

  const openConversation = () => {
    if (startConversation.isPending) {
      return;
    }
    startConversation.mutate(ad.id, {
      onSuccess: (conv) => {
        router.push(`/messages/${conv.uuid}`);
      },
      onError: (err) => {
        if (getApiErrorStatus(err) === 403) {
          // Le backend refuse tant que l'annonce n'est pas déverrouillée.
          handleUnlock(openConversation);
          return;
        }
        Alert.alert('Erreur', extractApiErrorMessage(err));
      },
    });
  };

  return (
    <>
      <Stack.Screen options={{ headerShown: false }} />
      <YStack flex={1} backgroundColor="$slate100">
        <Animated.ScrollView
          onScroll={Animated.event(
            [{ nativeEvent: { contentOffset: { y: scrollY } } }],
            { useNativeDriver: true },
          )}
          scrollEventThrottle={16}
          showsVerticalScrollIndicator={false}
          refreshControl={
            <RefreshControl
              refreshing={isRefetching}
              onRefresh={() => refetch()}
              tintColor={brand.primary}
              colors={[brand.primary]}
            />
          }
          contentContainerStyle={{
            paddingTop: heroHeight - OVERLAP,
            paddingBottom: insets.bottom + 110,
          }}
        >
          <DetailBody
            ad={ad}
            onShare={handleShare}
            onOpenDescription={() => setDescOpen(true)}
            onBookViewing={() => {
              if (!isAuthenticated) {
                router.push('/(auth)/login');
                return;
              }
              setBookingOpen(true);
            }}
          />
        </Animated.ScrollView>

        {/* Héro rendu APRÈS la ScrollView (donc au-dessus en z-order) pour
            que la galerie horizontale reçoive les gestes de swipe — sinon
            la ScrollView verticale les capte. La zone OVERLAP en bas laisse
            passer les touches vers le corps (pointerEvents box-none). */}
        <AnimatedHero
          ad={ad}
          width={width}
          heroHeight={heroHeight}
          carouselRef={carouselRef}
          activeImage={activeImage}
          setActiveImage={setActiveImage}
          translateY={heroTranslateY}
          scale={heroScale}
          onImagePress={(i) => {
            setActiveImage(i);
            setLightboxOpen(true);
          }}
        />

        {/* Floating top chrome — back arrow + share/compare/favorite */}
        <FloatingChrome
          insets={insets}
          ad={ad}
          chromeBgOpacity={chromeBgOpacity}
          onBack={() => router.back()}
          onShare={handleShare}
        />

        {/* Sticky bottom CTA — Message / Appeler / WhatsApp + Réserver */}
        <StickyCTA
          ad={ad}
          insets={insets}
          unlockPending={unlockAd.isPending}
          onUnlock={() => handleUnlock()}
          onMessage={() => {
            if (!isAuthenticated) {
              router.push('/(auth)/login');
              return;
            }
            if (!ad.is_unlocked) {
              // Contact gated par le déblocage crédits — on propose le
              // déverrouillage puis on ouvre la conversation dans la foulée.
              handleUnlock(openConversation);
              return;
            }
            openConversation();
          }}
          onCall={() => {
            if (!isAuthenticated) {
              router.push('/(auth)/login');
              return;
            }
            const phone = ad.user?.phone_number?.replace(/[\s\-()]/g, '');
            if (phone) {
              Linking.openURL(`tel:${phone}`).catch(() => router.push('/messages'));
            } else {
              router.push('/messages');
            }
          }}
          onWhatsApp={() => {
            if (!isAuthenticated) {
              router.push('/(auth)/login');
              return;
            }
            const phone = ad.user?.phone_number?.replace(/[\s\-()]/g, '');
            if (phone) {
              const text = encodeURIComponent(
                `Bonjour, je vous contacte au sujet de votre annonce "${ad.title}" sur KeyHome.`,
              );
              Linking.openURL(`https://wa.me/${phone.replace(/^\+/, '')}?text=${text}`).catch(
                () => router.push('/messages'),
              );
            } else {
              router.push('/messages');
            }
          }}
          onBookViewing={() => {
            if (!isAuthenticated) {
              router.push('/(auth)/login');
              return;
            }
            setBookingOpen(true);
          }}
        />

        {/* Description full-screen modal */}
        <Modal
          visible={descOpen}
          animationType="slide"
          presentationStyle="pageSheet"
          onRequestClose={() => setDescOpen(false)}
        >
          <DescriptionModal ad={ad} onClose={() => setDescOpen(false)} />
        </Modal>

        {/* Reserve viewing sheet */}
        <BookViewingSheet
          adId={ad.id}
          open={bookingOpen}
          onClose={() => setBookingOpen(false)}
        />

        {/* Galerie plein écran (tap sur le héro) */}
        {Array.isArray(ad.images) && ad.images.length > 0 && (
          <ImageLightbox
            images={ad.images}
            initialIndex={activeImage}
            visible={lightboxOpen}
            onClose={() => setLightboxOpen(false)}
          />
        )}
      </YStack>
    </>
  );
}

// ── Hero carousel ────────────────────────────────────────────────────
function AnimatedHero({
  ad,
  width,
  heroHeight,
  carouselRef,
  activeImage,
  setActiveImage,
  translateY,
  scale,
  onImagePress,
}: {
  ad: Ad;
  width: number;
  heroHeight: number;
  carouselRef: React.RefObject<FlatList<AdImage> | null>;
  activeImage: number;
  setActiveImage: (idx: number) => void;
  translateY: Animated.AnimatedInterpolation<number>;
  scale: Animated.AnimatedInterpolation<number>;
  onImagePress: (index: number) => void;
}) {
  const images = Array.isArray(ad.images) && ad.images.length > 0 ? ad.images : [];

  if (images.length === 0) {
    return (
      <YStack
        position="absolute"
        top={0}
        left={0}
        right={0}
        height={heroHeight}
        backgroundColor="$slate100"
        alignItems="center"
        justifyContent="center"
        gap={8}
      >
        <YStack
          width={64}
          height={64}
          borderRadius={32}
          backgroundColor="$background"
          alignItems="center"
          justifyContent="center"
        >
          <Maximize size={28} color="$slate500" />
        </YStack>
        <Paragraph color="$slate500" fontSize={13}>
          Aucune photo disponible
        </Paragraph>
      </YStack>
    );
  }

  return (
    <Animated.View
      style={{
        position: 'absolute',
        top: 0,
        left: 0,
        right: 0,
        height: heroHeight,
        backgroundColor: brand.slate100,
        transform: [{ translateY }, { scale }],
      }}
    >
      <FlatList
        ref={carouselRef}
        data={images}
        keyExtractor={(img) => String(img.id)}
        horizontal
        pagingEnabled
        showsHorizontalScrollIndicator={false}
        onMomentumScrollEnd={(e) => {
          const idx = Math.round(e.nativeEvent.contentOffset.x / width);
          setActiveImage(idx);
        }}
        getItemLayout={(_, index) => ({
          length: width,
          offset: width * index,
          index,
        })}
        renderItem={({ item, index }) => (
          <Pressable onPress={() => onImagePress(index)} accessibilityRole="imagebutton">
            <Image
              source={{ uri: item.large ?? item.url }}
              style={{ width, height: heroHeight }}
              contentFit="cover"
              transition={260}
              priority={index === 0 ? 'high' : 'normal'}
              accessibilityLabel={`Photo ${index + 1} sur ${images.length} — ${ad.title}`}
            />
          </Pressable>
        )}
      />

      {/* Photo counter — bottom-right */}
      {images.length > 1 && (
        <XStack
          position="absolute"
          bottom={OVERLAP + 14}
          right={16}
          paddingHorizontal={10}
          paddingVertical={4}
          borderRadius={999}
          backgroundColor="rgba(0,0,0,0.6)"
        >
          <Paragraph fontSize={12} fontWeight="700" color="white">
            {activeImage + 1} / {images.length}
          </Paragraph>
        </XStack>
      )}
    </Animated.View>
  );
}

// ── Floating chrome over hero ────────────────────────────────────────
function FloatingChrome({
  insets,
  ad,
  chromeBgOpacity,
  onBack,
  onShare,
}: {
  insets: { top: number };
  ad: Ad;
  chromeBgOpacity: Animated.AnimatedInterpolation<number>;
  onBack: () => void;
  onShare: () => void;
}) {
  const colors = useThemeColors();
  const { isAuthenticated } = useSession();
  const creditsBalance = useCreditsBalance(isAuthenticated);
  return (
    <YStack
      position="absolute"
      top={0}
      left={0}
      right={0}
      pointerEvents="box-none"
    >
      {/* Background that fades in as user scrolls past hero */}
      <Animated.View
        pointerEvents="none"
        style={{
          position: 'absolute',
          top: 0,
          left: 0,
          right: 0,
          height: insets.top + 56,
          backgroundColor: colors.chromeOverlay,
          borderBottomWidth: 1,
          borderBottomColor: colors.border,
          opacity: chromeBgOpacity,
        }}
      />
      <XStack
        paddingTop={insets.top + 8}
        paddingHorizontal={14}
        paddingBottom={8}
        justifyContent="space-between"
        alignItems="center"
        pointerEvents="box-none"
      >
        <ChromeIconButton onPress={onBack} accessibilityLabel={t('common.back')}>
          <ArrowLeft size={20} color={colors.mutedIcon} />
        </ChromeIconButton>
        <XStack gap={8} alignItems="center">
          {isAuthenticated && creditsBalance.data != null && (
            <XStack
              alignItems="center"
              gap={4}
              paddingHorizontal={9}
              height={34}
              borderRadius={17}
              backgroundColor="rgba(0,0,0,0.45)"
            >
              <Wallet size={13} color="white" />
              <Paragraph fontSize={12.5} fontWeight="800" color="white">
                {creditsBalance.data.toLocaleString('fr-FR')}
              </Paragraph>
            </XStack>
          )}
          <CompareButton ad={ad} size="medium" />
          <ChromeIconButton onPress={onShare} accessibilityLabel={t('ad.share')}>
            <Share2 size={18} color={colors.mutedIcon} />
          </ChromeIconButton>
          <FavoriteButton
            adId={ad.id}
            isFavorited={ad.is_favorited ?? false}
            size="medium"
          />
        </XStack>
      </XStack>
    </YStack>
  );
}

function ChromeIconButton({
  onPress,
  children,
  accessibilityLabel,
}: {
  onPress: () => void;
  children: React.ReactNode;
  accessibilityLabel: string;
}) {
  const colors = useThemeColors();
  return (
    <Pressable
      onPress={onPress}
      hitSlop={8}
      accessibilityRole="button"
      accessibilityLabel={accessibilityLabel}
    >
      <YStack
        width={36}
        height={36}
        borderRadius={18}
        backgroundColor={colors.chromeOverlay}
        alignItems="center"
        justifyContent="center"
      >
        {children}
      </YStack>
    </Pressable>
  );
}

// ── Body content (white card over hero) ──────────────────────────────
function DetailBody({
  ad,
  onShare,
  onOpenDescription,
  onBookViewing,
}: {
  ad: Ad;
  onShare: () => void;
  onOpenDescription: () => void;
  onBookViewing: () => void;
}) {
  const keyScore = useKeyScore(ad.id);
  const score = keyScore.data?.score;
  const scoreColor =
    score == null
      ? null
      : score >= 75
        ? brand.success
        : score >= 50
          ? brand.warning
          : brand.danger;

  const locationLabel = [ad.quarter?.name, ad.quarter?.city_name]
    .filter(Boolean)
    .join(', ');

  const updatedRelative = useMemo(() => {
    if (!ad.created_at) return null;
    try {
      return formatDistanceToNow(new Date(ad.created_at), {
        addSuffix: true,
        locale: fr,
      });
    } catch {
      return null;
    }
  }, [ad.created_at]);

  const availableFromLabel = useMemo(() => {
    if (!ad.available_from) return null;
    try {
      const date = new Date(ad.available_from);
      return `Dispo le ${date.toLocaleDateString('fr-FR', { day: 'numeric', month: 'long' })}`;
    } catch {
      return null;
    }
  }, [ad.available_from]);

  const features = [
    (ad.bedrooms ?? 0) > 0 && {
      icon: <Bed size={15} color="$slate700" />,
      label: `${ad.bedrooms} chambre${(ad.bedrooms ?? 0) > 1 ? 's' : ''}`,
    },
    (ad.bathrooms ?? 0) > 0 && {
      icon: <ShowerHead size={15} color="$slate700" />,
      label: `${ad.bathrooms} SDB`,
    },
    (ad.surface_area ?? 0) > 0 && {
      icon: <Maximize size={15} color="$slate700" />,
      label: `${ad.surface_area} m²`,
    },
    ad.has_parking && {
      icon: <ParkingCircle size={15} color="$slate700" />,
      label: 'Parking',
    },
  ].filter(Boolean) as { icon: React.ReactNode; label: string }[];

  const publisherName = ad.user?.firstname || 'Annonceur';
  const publisherInitial = publisherName.charAt(0).toUpperCase();

  const descTooLong = (ad.description ?? '').length > 220;

  return (
    <YStack
      backgroundColor="$background"
      borderTopLeftRadius={24}
      borderTopRightRadius={24}
      paddingHorizontal={20}
      paddingTop={20}
      paddingBottom={32}
      gap={18}
    >
      {/* Drag handle visual */}
      <YStack
        width={44}
        height={4}
        borderRadius={2}
        backgroundColor="$slate300"
        alignSelf="center"
        marginTop={-6}
      />

      {/* Action chip row */}
      <XStack gap={8} flexWrap="wrap" marginTop={4}>
        <ActionChip
          icon={<CalendarPlus size={14} color="white" />}
          label="Réserver une visite"
          onPress={onBookViewing}
          variant="primary"
        />
        <ActionChip
          icon={<Share2 size={14} color="$slate700" />}
          label="Partager"
          onPress={onShare}
        />
        {score != null && scoreColor && (
          <XStack
            alignItems="center"
            gap={5}
            paddingHorizontal={12}
            height={36}
            borderRadius={999}
            backgroundColor={`${scoreColor}1A`}
          >
            <ShieldCheck size={14} color={scoreColor} />
            <Paragraph fontSize={13} fontWeight="800" color={scoreColor}>
              KeyScore {Math.round(score)}
            </Paragraph>
          </XStack>
        )}
      </XStack>

      {/* Title + verified */}
      <YStack gap={4}>
        <XStack alignItems="flex-start" gap={6} flexWrap="wrap">
          <H2 flex={1} fontSize={22} lineHeight={28} fontWeight="700">
            {ad.title}
          </H2>
          {ad.is_verified && (
            <XStack
              alignItems="center"
              gap={4}
              paddingHorizontal={8}
              paddingVertical={4}
              borderRadius={999}
              backgroundColor={`${brand.success}15`}
              marginTop={2}
            >
              <CheckCircle2 size={13} color={brand.success} />
              <Paragraph fontSize={11} fontWeight="700" color={brand.success}>
                Vérifié
              </Paragraph>
            </XStack>
          )}
        </XStack>

        {/* Social proof line */}
        <XStack flexWrap="wrap" gap={12} marginTop={4}>
          {(ad.view_count ?? 0) > 0 && (
            <XStack alignItems="center" gap={4}>
              <Eye size={13} color="$slate500" />
              <Paragraph fontSize={12} color="$slate500">
                {ad.view_count} vue{(ad.view_count ?? 0) > 1 ? 's' : ''}
              </Paragraph>
            </XStack>
          )}
          {availableFromLabel && (
            <XStack alignItems="center" gap={4}>
              <CalendarCheck size={13} color="$slate500" />
              <Paragraph fontSize={12} color="$slate500">
                {availableFromLabel}
              </Paragraph>
            </XStack>
          )}
          {updatedRelative && (
            <Paragraph fontSize={12} color="$slate500">
              Publiée {updatedRelative}
            </Paragraph>
          )}
        </XStack>

        {/* Location row */}
        {locationLabel.length > 0 && (
          <XStack alignItems="center" gap={6} marginTop={6}>
            <MapPin size={15} color="$slate500" />
            <Paragraph fontSize={13.5} color="$slate700" flex={1}>
              {locationLabel}
              {ad.adresse ? ` — ${ad.adresse}` : ''}
            </Paragraph>
          </XStack>
        )}
      </YStack>

      {/* Feature pills */}
      {features.length > 0 && (
        <XStack flexWrap="wrap" gap={8}>
          {features.map((f, idx) => (
            <XStack
              key={idx}
              alignItems="center"
              gap={6}
              paddingHorizontal={12}
              paddingVertical={8}
              borderRadius={999}
              borderWidth={1}
              borderColor="$slate300"
              backgroundColor="$background"
            >
              {f.icon}
              <Paragraph fontSize={13} fontWeight="600" color="$slate700">
                {f.label}
              </Paragraph>
            </XStack>
          ))}
          {ad.type?.name && (
            <XStack
              alignItems="center"
              paddingHorizontal={12}
              paddingVertical={8}
              borderRadius={999}
              borderWidth={1}
              borderColor={brand.primary}
              backgroundColor={brand.primaryAlpha10}
            >
              <Paragraph fontSize={13} fontWeight="700" color={brand.primary}>
                {ad.type.name}
              </Paragraph>
            </XStack>
          )}
        </XStack>
      )}

      <SectionDivider />

      {/* Publisher card */}
      <PublisherCard ad={ad} publisherInitial={publisherInitial} publisherName={publisherName} updatedRelative={updatedRelative} />

      <SectionDivider />

      {/* Description */}
      <YStack gap={8}>
        <Paragraph fontSize={17} fontWeight="700" color="$slate900">
          Description
        </Paragraph>
        <Paragraph
          fontSize={14.5}
          color="$slate700"
          lineHeight={22}
          numberOfLines={DESCRIPTION_CLAMP}
        >
          {ad.description}
        </Paragraph>
        {descTooLong && (
          <Pressable onPress={onOpenDescription} hitSlop={8}>
            <Paragraph
              fontSize={14}
              fontWeight="700"
              color="$slate900"
              textDecorationLine="underline"
            >
              Lire la suite ›
            </Paragraph>
          </Pressable>
        )}
      </YStack>

      <SectionDivider />

      {/* KeyScore */}
      <KeyScoreSection adId={ad.id} />

      <SectionDivider />

      {/* Equipment list */}
      {ad.attributes && ad.attributes.length > 0 && (
        <>
          <PropertyAttributes attributes={ad.attributes} />
          <SectionDivider />
        </>
      )}

      {/* Infos supplémentaires (dépôt / bail / charges) — champs envoyés
          par l'API uniquement quand l'annonce est déverrouillée. */}
      {ad.is_unlocked && hasSupplementaryInfo(ad) && (
        <>
          <SupplementaryInfoCard ad={ad} />
          <SectionDivider />
        </>
      )}

      {/* Visite 3D — teaser verrouillé ou bouton vers la visite web. */}
      {ad.has_3d_tour && (
        <>
          <VirtualTourSection ad={ad} />
          <SectionDivider />
        </>
      )}

      {/* Location map */}
      {ad.location?.latitude != null && ad.location?.longitude != null && (
        <>
          <LocationMap
            latitude={ad.location.latitude}
            longitude={ad.location.longitude}
            quartierName={ad.quarter?.name}
            cityName={ad.quarter?.city_name}
            isLocked={!ad.is_unlocked}
          />
          <SectionDivider />
        </>
      )}

      {/* Address line — only fully visible once unlocked */}
      <YStack gap={6}>
        <Paragraph fontSize={15} fontWeight="700" color="$slate900">
          Adresse
        </Paragraph>
        {ad.is_unlocked ? (
          <Paragraph fontSize={14} color="$slate700" lineHeight={22}>
            {ad.adresse}
          </Paragraph>
        ) : (
          <Paragraph
            fontSize={13}
            color="$slate500"
            fontStyle="italic"
            lineHeight={20}
          >
            {t('ad.unlockToSeeAddress')}
          </Paragraph>
        )}
      </YStack>

      <SectionDivider />

      {/* Neighborhood */}
      <NeighborhoodScorecard adId={ad.id} />

      <SectionDivider />

      {/* Alerte de recherche préremplie avec les critères de l'annonce */}
      <SearchAlertButton ad={ad} />

      <SectionDivider />

      {/* Reviews */}
      <ReviewsSection
        adId={ad.id}
        fallbackRating={ad.rating}
        fallbackCount={ad.reviews_count}
      />

      <SectionDivider />

      {/* Similar ads */}
      <SimilarAdsCarousel adId={ad.id} />
    </YStack>
  );
}

function PublisherCard({
  ad,
  publisherInitial,
  publisherName,
  updatedRelative,
}: {
  ad: Ad;
  publisherInitial: string;
  publisherName: string;
  updatedRelative: string | null;
}) {
  const router = useRouter();
  const usernameOrId = ad.user?.username ?? ad.user?.id;
  const avatarUrl = resolveMediaUrl(ad.user?.avatar);
  const handlePress = () => {
    if (!usernameOrId) return;
    router.push({
      pathname: '/bailleurs/[username]',
      params: { username: usernameOrId },
    });
  };

  return (
    <Pressable onPress={handlePress} disabled={!usernameOrId}>
      <XStack gap={12} alignItems="center">
        <YStack
          width={48}
          height={48}
          borderRadius={24}
          overflow="hidden"
          backgroundColor={brand.primaryAlpha10}
          alignItems="center"
          justifyContent="center"
        >
          {avatarUrl ? (
            <Image
              source={{ uri: avatarUrl }}
              style={{ width: '100%', height: '100%' }}
              contentFit="cover"
              accessibilityLabel={publisherName}
            />
          ) : (
            <Paragraph fontSize={18} fontWeight="700" color={brand.primary}>
              {publisherInitial}
            </Paragraph>
          )}
        </YStack>
        <YStack flex={1} gap={2}>
          <XStack alignItems="center" gap={6} flexWrap="wrap">
            <Paragraph fontSize={15} fontWeight="700" color="$slate900">
              Publié par {publisherName}
            </Paragraph>
            {ad.user?.is_verified && (
              <CheckCircle2 size={14} color={brand.success} />
            )}
          </XStack>
          {updatedRelative && (
            <Paragraph fontSize={12} color="$slate500">
              {updatedRelative}
              {usernameOrId ? ' · Voir le profil' : ''}
            </Paragraph>
          )}
        </YStack>
      </XStack>
    </Pressable>
  );
}

function SectionDivider() {
  return (
    <YStack
      height={1}
      backgroundColor="$slate300"
      opacity={0.6}
      marginVertical={2}
    />
  );
}

function ActionChip({
  icon,
  label,
  onPress,
  variant = 'outline',
}: {
  icon: React.ReactNode;
  label: string;
  onPress: () => void;
  variant?: 'outline' | 'primary';
}) {
  const isPrimary = variant === 'primary';
  return (
    <Pressable onPress={onPress} hitSlop={6}>
      <XStack
        alignItems="center"
        gap={6}
        paddingHorizontal={12}
        paddingVertical={8}
        borderRadius={999}
        borderWidth={1}
        borderColor={isPrimary ? brand.primary : brand.slate300}
        backgroundColor={isPrimary ? brand.primary : 'transparent'}
      >
        {icon}
        <Paragraph fontSize={13} fontWeight="700" color={isPrimary ? 'white' : '$slate700'}>
          {label}
        </Paragraph>
      </XStack>
    </Pressable>
  );
}

// ── Sticky bottom CTA ────────────────────────────────────────────────
function StickyCTA({
  ad,
  insets,
  onMessage,
  onCall,
  onWhatsApp,
  onBookViewing,
  onUnlock,
  unlockPending,
}: {
  ad: Ad;
  insets: { bottom: number };
  onMessage: () => void;
  onCall: () => void;
  onWhatsApp: () => void;
  onBookViewing: () => void;
  onUnlock: () => void;
  unlockPending: boolean;
}) {
  const { format } = useCurrency();
  const periodLabel =
    ad.price_period === 'jour' ? t('ad.perDay') : t('ad.perMonth');
  // Téléphone visible = annonce déverrouillée → appel + WhatsApp possibles.
  const hasPhone = Boolean(ad.user?.phone_number);
  const unlockCost = ad.unlock_cost ?? 2;

  return (
    <YStack
      position="absolute"
      bottom={0}
      left={0}
      right={0}
      paddingHorizontal={16}
      paddingTop={12}
      paddingBottom={insets.bottom + 12}
      gap={10}
      backgroundColor="$background"
      borderTopWidth={1}
      borderTopColor="$borderColor"
    >
      <XStack alignItems="baseline" gap={4}>
        <Paragraph fontSize={19} fontWeight="800" color="$slate900" numberOfLines={1}>
          {ad.price != null ? format(ad.price) : '—'}
        </Paragraph>
        {ad.price != null && ad.transaction_type === 'location' && (
          <Paragraph fontSize={13} color="$slate500" fontWeight="500">
            {periodLabel}
          </Paragraph>
        )}
        {ad.user?.firstname && (
          <Paragraph fontSize={12} color="$slate500" numberOfLines={1} flex={1} textAlign="right">
            Publié par {ad.user.firstname}
          </Paragraph>
        )}
      </XStack>

      <XStack gap={8} alignItems="center">
        <CtaIconButton label="Message" onPress={onMessage}>
          <MessageCircle size={18} color={brand.primary} />
        </CtaIconButton>
        {ad.is_unlocked ? (
          <>
            {hasPhone ? (
              <>
                <CtaIconButton label="Appeler" onPress={onCall}>
                  <Phone size={18} color={brand.primary} />
                </CtaIconButton>
                <CtaIconButton label="WhatsApp" onPress={onWhatsApp}>
                  <MessageCircle size={18} color="#25D366" />
                </CtaIconButton>
              </>
            ) : null}
            <Button
              flex={1}
              size="$4"
              backgroundColor="$brand"
              color="$brandText"
              fontWeight="800"
              borderRadius={12}
              onPress={onBookViewing}
              icon={<CalendarPlus size={16} color="white" />}
              accessibilityRole="button"
            >
              Réserver
            </Button>
          </>
        ) : (
          <>
            <CtaIconButton label="Réserver une visite" onPress={onBookViewing}>
              <CalendarPlus size={18} color={brand.primary} />
            </CtaIconButton>
            <Button
              flex={1}
              size="$4"
              backgroundColor="$brand"
              color="$brandText"
              fontWeight="800"
              borderRadius={12}
              onPress={onUnlock}
              disabled={unlockPending}
              opacity={unlockPending ? 0.6 : 1}
              icon={<Wallet size={16} color="white" />}
              accessibilityRole="button"
            >
              {unlockPending
                ? 'Déblocage…'
                : `Débloquer · ${unlockCost} crédit${unlockCost > 1 ? 's' : ''}`}
            </Button>
          </>
        )}
      </XStack>
    </YStack>
  );
}

function CtaIconButton({
  label,
  onPress,
  children,
}: {
  label: string;
  onPress: () => void;
  children: React.ReactNode;
}) {
  return (
    <Pressable onPress={onPress} hitSlop={6} accessibilityRole="button" accessibilityLabel={label}>
      <YStack
        width={46}
        height={46}
        borderRadius={12}
        borderWidth={1}
        borderColor="$borderColor"
        alignItems="center"
        justifyContent="center"
        backgroundColor="$background"
      >
        {children}
      </YStack>
    </Pressable>
  );
}

// ── Description modal ────────────────────────────────────────────────
function DescriptionModal({ ad, onClose }: { ad: Ad; onClose: () => void }) {
  const insets = useSafeAreaInsets();

  return (
    <YStack flex={1} backgroundColor="$background">
      <XStack
        paddingTop={insets.top + 8}
        paddingHorizontal={16}
        paddingBottom={12}
        alignItems="center"
        gap={12}
        borderBottomWidth={1}
        borderBottomColor="$slate300"
      >
        <Pressable onPress={onClose} hitSlop={8} accessibilityLabel="Fermer">
          <YStack
            width={36}
            height={36}
            borderRadius={18}
            backgroundColor="$slate100"
            alignItems="center"
            justifyContent="center"
          >
            <X size={18} color="$slate700" />
          </YStack>
        </Pressable>
        <Paragraph fontSize={16} fontWeight="700" color="$slate900" flex={1}>
          Description
        </Paragraph>
      </XStack>
      <ScrollView
        contentContainerStyle={{
          paddingHorizontal: 20,
          paddingTop: 20,
          paddingBottom: insets.bottom + 24,
        }}
        showsVerticalScrollIndicator={false}
      >
        <Paragraph fontSize={20} fontWeight="700" color="$slate900" marginBottom={4}>
          À propos de ce logement
        </Paragraph>
        <Paragraph fontSize={13} color="$slate500" marginBottom={16}>
          {ad.title}
        </Paragraph>
        <SectionDivider />
        <Paragraph fontSize={15} color="$slate700" lineHeight={26} marginTop={16}>
          {ad.description}
        </Paragraph>
      </ScrollView>
    </YStack>
  );
}
