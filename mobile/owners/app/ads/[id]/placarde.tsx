import { Download, Printer, QrCode, Share2 } from '@tamagui/lucide-icons';
import { Image } from 'expo-image';
import { useLocalSearchParams } from 'expo-router';
import { useState } from 'react';
import { Alert } from 'react-native';
import { Button, Paragraph, Spinner, XStack, YStack } from 'tamagui';

import { ENDPOINTS } from '@/api/endpoints';
import { ScreenHeader } from '@/components/ScreenHeader';
import { useAd } from '@/hooks/useAd';
import { useAdQr } from '@/hooks/useMarketing';
import {
  downloadAuthedFile,
  printLocalFile,
  shareLocalFile,
} from '@/utils/documents';
import { brand } from '@/theme/tokens';
import { t } from '@/i18n';

type Tab = 'placarde' | 'qr';

/**
 * Pancarte (yard sign) + QR screen. The pancarte is a server-rendered
 * A5 PDF with the ad's QR code — owners download / print / share it to
 * post physically on the property. The QR tab shows the scannable code
 * inline (data-URI) for quick on-screen sharing.
 */
export default function PlacardeScreen() {
  const params = useLocalSearchParams<{ id: string; tab?: string }>();
  const id = params.id;
  const { data: ad } = useAd(id);
  const qr = useAdQr(id);

  const [tab, setTab] = useState<Tab>(params.tab === 'qr' ? 'qr' : 'placarde');
  const [busy, setBusy] = useState<'download' | 'print' | 'share' | null>(null);

  const filename = `pancarte-${ad?.slug ?? id}.pdf`;

  const withFile = async (
    action: 'download' | 'print' | 'share',
    run: (uri: string) => Promise<void>,
  ) => {
    if (!id) return;
    setBusy(action);
    try {
      const uri = await downloadAuthedFile(ENDPOINTS.my.adPlacarde(id), filename);
      await run(uri);
    } catch (err) {
      Alert.alert(t('common.error'), err instanceof Error ? err.message : 'Erreur');
    } finally {
      setBusy(null);
    }
  };

  return (
    <YStack flex={1} backgroundColor="$background">
      <ScreenHeader title={t('placarde.title')} subtitle={ad?.title} />

      {/* Tabs */}
      <XStack padding={16} gap={8}>
        <TabButton active={tab === 'placarde'} label={t('placarde.title')} icon={<Printer size={16} color={tab === 'placarde' ? 'white' : brand.slate700} />} onPress={() => setTab('placarde')} />
        <TabButton active={tab === 'qr'} label={t('placarde.qrTitle')} icon={<QrCode size={16} color={tab === 'qr' ? 'white' : brand.slate700} />} onPress={() => setTab('qr')} />
      </XStack>

      {tab === 'placarde' ? (
        <YStack flex={1} paddingHorizontal={16} gap={16}>
          <YStack
            backgroundColor={brand.primaryAlpha10}
            borderRadius={18}
            padding={24}
            alignItems="center"
            gap={12}
          >
            <YStack width={80} height={80} borderRadius={20} backgroundColor="$background" alignItems="center" justifyContent="center">
              <Printer size={40} color={brand.primary} />
            </YStack>
            <Paragraph fontSize={18} fontWeight="900" color="$slate900" textAlign="center">
              {t('placarde.ready')}
            </Paragraph>
            <Paragraph fontSize={13.5} color="$slate500" textAlign="center" lineHeight={20}>
              {t('placarde.subtitle')}
            </Paragraph>
          </YStack>

          <YStack gap={10}>
            <Button
              size="$5"
              backgroundColor="$brand"
              color="white"
              fontWeight="800"
              borderRadius={14}
              disabled={busy !== null}
              icon={busy === 'print' ? <Spinner color="white" /> : <Printer size={18} color="white" />}
              onPress={() => withFile('print', printLocalFile)}
            >
              {t('placarde.print')}
            </Button>
            <XStack gap={10}>
              <Button
                flex={1}
                size="$5"
                chromeless
                borderWidth={1}
                borderColor="$slate300"
                borderRadius={14}
                disabled={busy !== null}
                icon={busy === 'share' ? <Spinner /> : <Share2 size={18} color={brand.slate700} />}
                onPress={() => withFile('share', shareLocalFile)}
              >
                <Paragraph fontWeight="700" color="$slate700">
                  {t('placarde.share')}
                </Paragraph>
              </Button>
              <Button
                flex={1}
                size="$5"
                chromeless
                borderWidth={1}
                borderColor="$slate300"
                borderRadius={14}
                disabled={busy !== null}
                icon={busy === 'download' ? <Spinner /> : <Download size={18} color={brand.slate700} />}
                onPress={() => withFile('download', shareLocalFile)}
              >
                <Paragraph fontWeight="700" color="$slate700">
                  {t('placarde.download')}
                </Paragraph>
              </Button>
            </XStack>
          </YStack>
        </YStack>
      ) : (
        <YStack flex={1} alignItems="center" paddingHorizontal={16} gap={16}>
          {qr.isLoading ? (
            <Spinner color={brand.primary} size="large" />
          ) : qr.data?.qr_data_uri ? (
            <>
              <YStack
                backgroundColor="white"
                padding={20}
                borderRadius={20}
                borderWidth={1}
                borderColor="$slate300"
                marginTop={12}
              >
                <Image
                  source={{ uri: qr.data.qr_data_uri }}
                  style={{ width: 220, height: 220 }}
                  contentFit="contain"
                />
              </YStack>
              <Paragraph fontSize={14} fontWeight="700" color="$slate900" textAlign="center">
                {t('placarde.qrTitle')}
              </Paragraph>
              <Paragraph fontSize={13} color="$slate500" textAlign="center" lineHeight={20}>
                {t('placarde.qrSubtitle')}
              </Paragraph>
              {qr.data.ad_url ? (
                <Paragraph fontSize={12} color="$brand" textAlign="center" numberOfLines={1}>
                  {qr.data.ad_url}
                </Paragraph>
              ) : null}
            </>
          ) : (
            <Paragraph color="$slate500" marginTop={24}>
              QR code indisponible.
            </Paragraph>
          )}
        </YStack>
      )}
    </YStack>
  );
}

function TabButton({
  active,
  label,
  icon,
  onPress,
}: {
  active: boolean;
  label: string;
  icon: React.ReactElement;
  onPress: () => void;
}) {
  return (
    <Button
      flex={1}
      size="$4"
      backgroundColor={active ? '$brand' : '$slate100'}
      borderRadius={12}
      icon={icon}
      onPress={onPress}
    >
      <Paragraph fontSize={13.5} fontWeight="700" color={active ? 'white' : '$slate700'}>
        {label}
      </Paragraph>
    </Button>
  );
}
