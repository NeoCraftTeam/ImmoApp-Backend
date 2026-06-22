import {
  Copy,
  Eye,
  EyeOff,
  Pencil,
  Printer,
  QrCode,
  Repeat,
  Rocket,
  Send,
  Trash2,
} from '@tamagui/lucide-icons';
import { Image } from 'expo-image';
import { useLocalSearchParams, useRouter } from 'expo-router';
import { useState } from 'react';
import { Alert, Dimensions, Pressable, ScrollView } from 'react-native';
import { Button, H1, Paragraph, Spinner, XStack, YStack } from 'tamagui';

import { extractApiErrorMessage } from '@/api/client';
import { BoostSheet } from '@/components/ads/BoostSheet';
import { ScreenHeader } from '@/components/ScreenHeader';
import { StatusBadge } from '@/components/StatusBadge';
import { useAd } from '@/hooks/useAd';
import { useBoostStatus, useRemoveBoost } from '@/hooks/useBoost';
import {
  useDeleteAd,
  useDuplicateAd,
  usePublishAd,
  useSetAdStatus,
  useToggleVisibility,
} from '@/hooks/useAdMutations';
import { brand } from '@/theme/tokens';
import { formatDate, formatFcfa } from '@/utils/format';
import { t } from '@/i18n';
import type { AdStatus } from '@/types/ad';

const { width } = Dimensions.get('window');

const TRANSITIONS: Record<AdStatus, AdStatus[]> = {
  draft: ['available'],
  pending: ['available'],
  available: ['reserved', 'rent', 'sold'],
  reserved: ['available', 'rent', 'sold'],
  rent: ['available'],
  sold: ['available'],
  declined: ['available'],
};

const STATUS_LABEL: Record<AdStatus, string> = {
  draft: 'Brouillon',
  pending: 'En attente',
  available: 'Disponible',
  reserved: 'Réservé',
  rent: 'Loué',
  sold: 'Vendu',
  declined: 'Refusé',
};

export default function AdDetailScreen() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const router = useRouter();
  const { data: ad, isLoading } = useAd(id);
  const boostStatus = useBoostStatus(id);

  const publish = usePublishAd();
  const setStatus = useSetAdStatus();
  const toggleVisibility = useToggleVisibility();
  const duplicate = useDuplicateAd();
  const removeBoost = useRemoveBoost(id);
  const deleteAd = useDeleteAd();

  const [boostOpen, setBoostOpen] = useState(false);

  if (isLoading || !ad) {
    return (
      <YStack flex={1} backgroundColor="$background">
        <ScreenHeader title={t('common.loading')} />
        <YStack flex={1} alignItems="center" justifyContent="center">
          <Spinner color={brand.primary} size="large" />
        </YStack>
      </YStack>
    );
  }

  const isDraft = ad.status === 'draft';

  const handlePublish = () => {
    Alert.alert(t('ads.actions.publish'), t('ads.publishConfirm'), [
      { text: t('common.cancel'), style: 'cancel' },
      {
        text: t('ads.actions.publish'),
        onPress: () =>
          publish.mutate(ad.id, {
            onError: (err) => Alert.alert(t('common.error'), extractApiErrorMessage(err)),
          }),
      },
    ]);
  };

  const handleChangeStatus = () => {
    const options = TRANSITIONS[ad.status] ?? [];
    if (options.length === 0) {
      Alert.alert(t('ads.actions.changeStatus'), 'Aucune transition disponible.');
      return;
    }
    Alert.alert(
      t('ads.actions.changeStatus'),
      undefined,
      [
        ...options.map((s) => ({
          text: STATUS_LABEL[s],
          onPress: () =>
            setStatus.mutate(
              { id: ad.id, status: s },
              { onError: (err) => Alert.alert(t('common.error'), extractApiErrorMessage(err)) },
            ),
        })),
        { text: t('common.cancel'), style: 'cancel' as const },
      ],
    );
  };

  const handleDelete = () => {
    Alert.alert(t('ads.actions.delete'), t('ads.deleteConfirm'), [
      { text: t('common.cancel'), style: 'cancel' },
      {
        text: t('common.delete'),
        style: 'destructive',
        onPress: () =>
          deleteAd.mutate(ad.id, {
            onSuccess: () => router.back(),
            onError: (err) => Alert.alert(t('common.error'), extractApiErrorMessage(err)),
          }),
      },
    ]);
  };

  const handleDuplicate = () => {
    duplicate.mutate(ad.id, {
      onSuccess: (created) => router.push(`/ads/${created.id}/edit` as never),
      onError: (err) => Alert.alert(t('common.error'), extractApiErrorMessage(err)),
    });
  };

  const handleRemoveBoost = () => {
    removeBoost.mutate(undefined, {
      onError: (err) => Alert.alert(t('common.error'), extractApiErrorMessage(err)),
    });
  };

  const boosted = boostStatus.data?.is_boosted;

  return (
    <YStack flex={1} backgroundColor="$background">
      <ScreenHeader title={t('common.edit')} />
      <ScrollView contentContainerStyle={{ paddingBottom: 32 }} showsVerticalScrollIndicator={false}>
        {/* Image carousel */}
        {ad.images && ad.images.length > 0 ? (
          <ScrollView horizontal pagingEnabled showsHorizontalScrollIndicator={false}>
            {ad.images.map((img) => (
              <Image
                key={String(img.id)}
                source={{ uri: img.url }}
                style={{ width, height: 240 }}
                contentFit="cover"
                transition={150}
              />
            ))}
          </ScrollView>
        ) : (
          <YStack width={width} height={180} backgroundColor="$slate100" alignItems="center" justifyContent="center">
            <Paragraph color="$slate500">Aucune photo</Paragraph>
          </YStack>
        )}

        {/* Title block */}
        <YStack paddingHorizontal={16} paddingTop={16} gap={8}>
          <XStack alignItems="center" gap={8}>
            <StatusBadge status={ad.status} />
            {ad.is_visible === false ? (
              <Paragraph fontSize={11} fontWeight="700" color="$slate500">
                Masquée
              </Paragraph>
            ) : null}
          </XStack>
          <H1 fontSize={22} fontWeight="900">
            {ad.title || 'Sans titre'}
          </H1>
          <Paragraph fontSize={20} fontWeight="900" color="$brand">
            {formatFcfa(ad.price)}
            {ad.price_period ? (
              <Paragraph fontSize={14} color="$slate500" fontWeight="600">
                {' '}
                /{ad.price_period}
              </Paragraph>
            ) : null}
          </Paragraph>
          <Paragraph fontSize={14} color="$slate500">
            {ad.quarter?.name ? `${ad.quarter.name} · ` : ''}
            {ad.adresse}
          </Paragraph>

          <XStack gap={18} marginTop={6}>
            <Stat label="Vues" value={ad.view_count ?? 0} />
            <Stat label="Avis" value={ad.reviews_count ?? 0} />
            {ad.surface_area ? <Stat label="m²" value={ad.surface_area} /> : null}
            {ad.bedrooms != null ? <Stat label="Chambres" value={ad.bedrooms} /> : null}
          </XStack>
        </YStack>

        {/* Boost banner */}
        {boosted ? (
          <XStack
            marginHorizontal={16}
            marginTop={16}
            padding={14}
            borderRadius={14}
            backgroundColor={brand.accentAlpha10}
            alignItems="center"
            gap={10}
          >
            <Rocket size={20} color={brand.accent} />
            <YStack flex={1}>
              <Paragraph fontSize={14} fontWeight="800" color={brand.accentDark}>
                {t('boost.active')}
              </Paragraph>
              {boostStatus.data?.boost_expires_at ? (
                <Paragraph fontSize={12} color="$slate500">
                  {t('boost.expiresOn')} {formatDate(boostStatus.data.boost_expires_at)}
                </Paragraph>
              ) : null}
            </YStack>
            <Pressable onPress={handleRemoveBoost} hitSlop={8}>
              <Paragraph fontSize={12} fontWeight="700" color="$danger">
                {t('boost.remove')}
              </Paragraph>
            </Pressable>
          </XStack>
        ) : null}

        {/* Primary CTAs */}
        <YStack paddingHorizontal={16} marginTop={18} gap={10}>
          {isDraft ? (
            <Button
              size="$5"
              backgroundColor="$brand"
              color="white"
              fontWeight="800"
              borderRadius={14}
              icon={publish.isPending ? <Spinner color="white" /> : <Send size={18} color="white" />}
              disabled={publish.isPending}
              onPress={handlePublish}
            >
              {t('ads.actions.publish')}
            </Button>
          ) : null}
          <XStack gap={10}>
            <Button
              flex={1}
              size="$4"
              chromeless
              borderWidth={1}
              borderColor="$slate300"
              borderRadius={12}
              icon={<Pencil size={16} color={brand.slate700} />}
              onPress={() => router.push(`/ads/${ad.id}/edit` as never)}
            >
              <Paragraph fontWeight="700" color="$slate700">
                {t('ads.actions.edit')}
              </Paragraph>
            </Button>
            {!boosted ? (
              <Button
                flex={1}
                size="$4"
                backgroundColor={brand.accent}
                color="white"
                borderRadius={12}
                fontWeight="700"
                icon={<Rocket size={16} color="white" />}
                onPress={() => setBoostOpen(true)}
              >
                {t('ads.actions.boost')}
              </Button>
            ) : null}
          </XStack>
        </YStack>

        {/* Action grid */}
        <YStack paddingHorizontal={16} marginTop={20} gap={10}>
          <Paragraph fontSize={16} fontWeight="800" color="$slate900">
            Gérer
          </Paragraph>
          <XStack flexWrap="wrap" gap={10}>
            <ActionTile icon={<Printer size={20} color={brand.primary} />} label={t('ads.actions.placarde')} onPress={() => router.push(`/ads/${ad.id}/placarde` as never)} />
            <ActionTile icon={<QrCode size={20} color={brand.primary} />} label={t('ads.actions.qr')} onPress={() => router.push(`/ads/${ad.id}/placarde?tab=qr` as never)} />
            <ActionTile icon={<Repeat size={20} color={brand.secondary} />} label={t('ads.actions.changeStatus')} onPress={handleChangeStatus} />
            <ActionTile
              icon={ad.is_visible === false ? <Eye size={20} color={brand.slate700} /> : <EyeOff size={20} color={brand.slate700} />}
              label={ad.is_visible === false ? t('ads.actions.show') : t('ads.actions.hide')}
              onPress={() =>
                toggleVisibility.mutate(ad.id, {
                  onError: (err) => Alert.alert(t('common.error'), extractApiErrorMessage(err)),
                })
              }
            />
            <ActionTile icon={<Copy size={20} color={brand.slate700} />} label={t('ads.actions.duplicate')} onPress={handleDuplicate} />
            <ActionTile icon={<Trash2 size={20} color={brand.danger} />} label={t('ads.actions.delete')} danger onPress={handleDelete} />
          </XStack>
        </YStack>

        {/* Description */}
        {ad.description ? (
          <YStack paddingHorizontal={16} marginTop={22} gap={8}>
            <Paragraph fontSize={16} fontWeight="800" color="$slate900">
              Description
            </Paragraph>
            <Paragraph fontSize={14} color="$slate700" lineHeight={21}>
              {ad.description}
            </Paragraph>
          </YStack>
        ) : null}
      </ScrollView>

      <BoostSheet adId={ad.id} open={boostOpen} onClose={() => setBoostOpen(false)} onBoosted={() => boostStatus.refetch()} />
    </YStack>
  );
}

function Stat({ label, value }: { label: string; value: number | string }) {
  return (
    <YStack>
      <Paragraph fontSize={16} fontWeight="900" color="$slate900">
        {value}
      </Paragraph>
      <Paragraph fontSize={12} color="$slate500">
        {label}
      </Paragraph>
    </YStack>
  );
}

function ActionTile({
  icon,
  label,
  onPress,
  danger,
}: {
  icon: React.ReactNode;
  label: string;
  onPress: () => void;
  danger?: boolean;
}) {
  return (
    <Pressable onPress={onPress} style={{ width: '47%' }}>
      <XStack
        alignItems="center"
        gap={10}
        padding={14}
        borderRadius={12}
        borderWidth={1}
        borderColor={danger ? '$danger' : '$slate300'}
        backgroundColor="$background"
      >
        {icon}
        <Paragraph fontSize={13.5} fontWeight="700" color={danger ? '$danger' : '$slate900'} numberOfLines={1}>
          {label}
        </Paragraph>
      </XStack>
    </Pressable>
  );
}
