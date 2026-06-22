import { useEffect, useState } from 'react';
import { Download, Mail, QrCode, Share2 } from '@tamagui/lucide-icons';
import { Image } from 'expo-image';
import * as FileSystem from 'expo-file-system/legacy';
import * as Sharing from 'expo-sharing';
import { Alert, ScrollView } from 'react-native';
import { Button, Paragraph, Spinner, XStack, YStack } from 'tamagui';

import { apiClient } from '@/api/client';
import { ENDPOINTS } from '@/api/endpoints';
import { useSession } from '@/auth/SessionProvider';
import { useMe } from '@/hooks/useMe';
import { useProfileQr } from '@/hooks/useMarketing';
import { ScreenHeader } from '@/components/ScreenHeader';
import { reportError, trackEvent } from '@/services/monitoring';
import { brand } from '@/theme/tokens';

/**
 * Carte de visite digitale du bailleur. Affiche le QR du profil +
 * informations clés. Permet de télécharger le PDF officiel
 * `/my/profile/business-card` et de le partager via le sheet natif.
 */
export default function BusinessCardScreen() {
  const { isAuthenticated } = useSession();
  const me = useMe(isAuthenticated);
  const qr = useProfileQr(isAuthenticated);

  const [downloading, setDownloading] = useState(false);
  const [localUri, setLocalUri] = useState<string | null>(null);

  const downloadPdf = async () => {
    setDownloading(true);
    try {
      const token = apiClient.defaults.headers.common['Authorization'];
      const baseUrl = apiClient.defaults.baseURL ?? '';
      const dest = `${FileSystem.cacheDirectory}business-card.pdf`;
      const result = await FileSystem.downloadAsync(
        `${baseUrl}${ENDPOINTS.my.businessCard}`,
        dest,
        token
          ? { headers: { Authorization: String(token) } }
          : undefined,
      );
      setLocalUri(result.uri);
      trackEvent('owner.business_card.download');
      return result.uri;
    } catch (err) {
      reportError(err);
      Alert.alert('Erreur', 'Le téléchargement de la carte a échoué.');
      return null;
    } finally {
      setDownloading(false);
    }
  };

  const share = async () => {
    const uri = localUri ?? (await downloadPdf());
    if (!uri) return;
    try {
      if (await Sharing.isAvailableAsync()) {
        await Sharing.shareAsync(uri, {
          dialogTitle: 'Partager ma carte de visite',
          mimeType: 'application/pdf',
        });
      }
    } catch (err) {
      reportError(err);
    }
  };

  const fullName = `${me.data?.firstname ?? ''} ${me.data?.lastname ?? ''}`.trim();

  // Pre-fetch business card preview pour réduire latency au tap "Partager"
  useEffect(() => {
    // intentionnel — pas de pre-fetch agressif pour éviter d'éclater la quota Sentry
  }, []);

  return (
    <YStack flex={1} backgroundColor="$background">
      <ScreenHeader title="Carte de visite" subtitle="Votre profil partageable en PDF + QR" />

      <ScrollView contentContainerStyle={{ padding: 16, paddingBottom: 32, gap: 16 }}>
        {/* Card preview */}
        <YStack
          padding={22}
          gap={14}
          borderRadius={20}
          backgroundColor={brand.primary}
          alignItems="center"
        >
          <YStack
            width={180}
            height={180}
            borderRadius={16}
            backgroundColor="white"
            alignItems="center"
            justifyContent="center"
            padding={10}
          >
            {qr.isLoading ? (
              <Spinner color={brand.primary} />
            ) : qr.data?.qr_data_uri ? (
              <Image
                source={{ uri: qr.data.qr_data_uri }}
                style={{ width: '100%', height: '100%' }}
                contentFit="contain"
              />
            ) : (
              <QrCode size={56} color={brand.primary} />
            )}
          </YStack>

          <YStack alignItems="center" gap={3}>
            <Paragraph fontSize={20} fontWeight="900" color="white">
              {fullName || 'Bailleur'}
            </Paragraph>
            {me.data?.agency_name ? (
              <Paragraph fontSize={13} color="rgba(255,255,255,0.85)" fontWeight="700">
                {me.data.agency_name}
              </Paragraph>
            ) : null}
            <XStack alignItems="center" gap={6} marginTop={6}>
              <Mail size={13} color="rgba(255,255,255,0.85)" />
              <Paragraph fontSize={12} color="rgba(255,255,255,0.85)">
                {me.data?.email ?? ''}
              </Paragraph>
            </XStack>
            {me.data?.phone_number ? (
              <Paragraph fontSize={12} color="rgba(255,255,255,0.85)">
                {me.data.phone_number}
              </Paragraph>
            ) : null}
          </YStack>
        </YStack>

        {/* CTA */}
        <YStack gap={10}>
          <Button
            size="$5"
            backgroundColor="$brand"
            color="white"
            fontWeight="800"
            borderRadius={14}
            disabled={downloading}
            onPress={downloadPdf}
            icon={
              downloading ? (
                <Spinner color="white" />
              ) : (
                <Download size={16} color="white" />
              )
            }
          >
            {downloading ? 'Téléchargement…' : 'Télécharger le PDF'}
          </Button>

          <Button
            size="$5"
            chromeless
            borderWidth={1}
            borderColor="$brand"
            color="$brand"
            fontWeight="800"
            borderRadius={14}
            onPress={share}
            icon={<Share2 size={16} color={brand.primary} />}
          >
            Partager
          </Button>
        </YStack>

        <YStack
          padding={14}
          gap={6}
          borderRadius={12}
          backgroundColor={brand.primaryAlpha10}
        >
          <Paragraph fontSize={12.5} fontWeight="800" color={brand.primaryHover}>
            Conseil
          </Paragraph>
          <Paragraph fontSize={12} color="$slate700" lineHeight={18}>
            Imprimez la carte ou affichez le QR depuis votre téléphone — vos
            prospects la scannent et accèdent instantanément à votre profil
            avec toutes vos annonces actives.
          </Paragraph>
        </YStack>
      </ScrollView>
    </YStack>
  );
}
