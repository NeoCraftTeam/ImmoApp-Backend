import {
  CalendarRange,
  Download,
  FileText,
  MoreVertical,
  Plus,
  RefreshCw,
  User,
  X,
} from '@tamagui/lucide-icons';
import { useState } from 'react';
import { Alert, FlatList, Pressable } from 'react-native';
import {
  Button,
  Input,
  Paragraph,
  Sheet,
  Spinner,
  XStack,
  YStack,
} from 'tamagui';

import { extractApiErrorMessage } from '@/api/extract-error';
import { useSession } from '@/auth/SessionProvider';
import { EmptyState } from '@/components/EmptyState';
import { ScreenHeader } from '@/components/ScreenHeader';
import {
  useGenerateLease,
  useRenewLease,
  useTerminateLease,
} from '@/hooks/useLeaseActions';
import { useLeases } from '@/hooks/useLeases';
import { useMyAds } from '@/hooks/useMyAds';
import { useTenants } from '@/hooks/useTenants';
import { t } from '@/i18n';
import { ENDPOINTS } from '@/api/endpoints';
import { brand } from '@/theme/tokens';
import { downloadAuthedFile, shareLocalFile } from '@/utils/documents';
import { formatDate, formatFcfa } from '@/utils/format';
import type { LeaseContract, LeaseStatus } from '@/types/owner';

const STATUS_COLOR: Record<LeaseStatus, string> = {
  draft: brand.slate500,
  active: brand.success,
  expired: brand.accentDark,
  terminated: brand.danger,
  archived: brand.slate500,
};

export default function LeaseContractsScreen() {
  const { isAuthenticated } = useSession();
  const { data: leases, isLoading } = useLeases(isAuthenticated);
  const [generating, setGenerating] = useState(false);
  const [renewing, setRenewing] = useState<LeaseContract | null>(null);
  const [terminating, setTerminating] = useState<LeaseContract | null>(null);

  return (
    <YStack flex={1} backgroundColor="$background">
      <ScreenHeader
        title={t('leases.title')}
        right={
          <Button
            size="$3"
            chromeless
            borderRadius={999}
            backgroundColor="$brand"
            color="white"
            paddingHorizontal={12}
            onPress={() => setGenerating(true)}
            icon={<Plus size={14} color="white" />}
          >
            Générer
          </Button>
        }
      />

      {isLoading ? (
        <YStack flex={1} alignItems="center" justifyContent="center">
          <Spinner color={brand.primary} size="large" />
        </YStack>
      ) : (
        <FlatList
          data={leases ?? []}
          keyExtractor={(item) => item.id}
          contentContainerStyle={{
            paddingHorizontal: 16,
            paddingTop: 16,
            paddingBottom: 32,
            gap: 12,
          }}
          renderItem={({ item }) => (
            <LeaseCard
              lease={item}
              onRenew={() => setRenewing(item)}
              onTerminate={() => setTerminating(item)}
            />
          )}
          ListEmptyComponent={
            <YStack height={420}>
              <EmptyState
                icon={<FileText size={28} color={brand.primary} />}
                title={t('leases.empty')}
                hint="Vos contrats de bail signés avec vos locataires apparaîtront ici."
                ctaLabel="Générer un bail"
                onPressCta={() => setGenerating(true)}
              />
            </YStack>
          }
        />
      )}

      <GenerateLeaseSheet open={generating} onOpenChange={setGenerating} />
      <RenewLeaseSheet lease={renewing} onClose={() => setRenewing(null)} />
      <TerminateLeaseSheet
        lease={terminating}
        onClose={() => setTerminating(null)}
      />
    </YStack>
  );
}

function LeaseCard({
  lease,
  onRenew,
  onTerminate,
}: {
  lease: LeaseContract;
  onRenew: () => void;
  onTerminate: () => void;
}) {
  const [menu, setMenu] = useState(false);
  const [downloading, setDownloading] = useState(false);
  const color = STATUS_COLOR[lease.status] ?? brand.slate500;
  const tenantName = lease.tenant_name?.trim() ?? '';
  const canAct = lease.status === 'active' || lease.status === 'expired';

  const downloadPdf = async () => {
    setDownloading(true);
    try {
      const uri = await downloadAuthedFile(
        ENDPOINTS.my.leaseDownload(lease.id),
        `bail-${lease.contract_number ?? lease.id}.pdf`,
      );
      await shareLocalFile(uri);
    } catch (err) {
      Alert.alert(t('common.error'), err instanceof Error ? err.message : 'Erreur');
    } finally {
      setDownloading(false);
    }
  };

  return (
    <YStack
      borderWidth={1}
      borderColor="$slate300"
      borderRadius={16}
      padding={14}
      gap={10}
      backgroundColor="$background"
    >
      <XStack alignItems="center" justifyContent="space-between" gap={8}>
        <Paragraph fontSize={17} fontWeight="900" color="$slate900" flex={1} numberOfLines={1}>
          {formatFcfa(lease.monthly_rent)}
          <Paragraph fontSize={13} fontWeight="600" color="$slate500">
            {' '}
            {t('ads.perMonth')}
          </Paragraph>
        </Paragraph>
        <XStack
          backgroundColor={`${color}1A`}
          paddingHorizontal={10}
          paddingVertical={4}
          borderRadius={999}
        >
          <Paragraph fontSize={11} fontWeight="800" color={color}>
            {t(`leases.status.${lease.status}`)}
          </Paragraph>
        </XStack>
        {canAct ? (
          <Pressable
            onPress={() => setMenu((v) => !v)}
            hitSlop={6}
            accessibilityLabel="Actions du bail"
          >
            <MoreVertical size={18} color={brand.slate500} />
          </Pressable>
        ) : null}
      </XStack>

      <XStack alignItems="center" gap={8}>
        <CalendarRange size={14} color={brand.slate500} />
        <Paragraph fontSize={13} color="$slate700">
          {formatDate(lease.lease_start)} – {formatDate(lease.lease_end)}
        </Paragraph>
      </XStack>

      {tenantName ? (
        <XStack alignItems="center" gap={8}>
          <User size={14} color={brand.slate500} />
          <Paragraph fontSize={13} color="$slate700" numberOfLines={1} flex={1}>
            {tenantName}
          </Paragraph>
          {lease.contract_number ? (
            <Paragraph fontSize={11} color="$slate500">
              N° {lease.contract_number}
            </Paragraph>
          ) : null}
        </XStack>
      ) : null}

      <XStack gap={8} marginTop={2} flexWrap="wrap">
        <Button
          size="$2"
          backgroundColor="$slate100"
          color={brand.slate700}
          fontWeight="800"
          borderRadius={10}
          disabled={downloading}
          onPress={() => void downloadPdf()}
          icon={
            downloading ? (
              <Spinner size="small" />
            ) : (
              <Download size={12} color={brand.slate700} />
            )
          }
        >
          PDF
        </Button>
        {menu ? (
          <>
            <Button
              size="$2"
              backgroundColor={brand.primaryAlpha10}
              color={brand.primary}
              fontWeight="800"
              borderRadius={10}
              onPress={() => {
                setMenu(false);
                onRenew();
              }}
              icon={<RefreshCw size={12} color={brand.primary} />}
            >
              Renouveler
            </Button>
            <Button
              size="$2"
              backgroundColor={`${brand.danger}1A`}
              color={brand.danger}
              fontWeight="800"
              borderRadius={10}
              onPress={() => {
                setMenu(false);
                onTerminate();
              }}
              icon={<X size={12} color={brand.danger} />}
            >
              Résilier
            </Button>
          </>
        ) : null}
      </XStack>
    </YStack>
  );
}

function GenerateLeaseSheet({
  open,
  onOpenChange,
}: {
  open: boolean;
  onOpenChange: (v: boolean) => void;
}) {
  const ads = useMyAds({}, open);
  const tenants = useTenants(open);
  const generate = useGenerateLease();
  const flatAds = Array.isArray(ads.data?.pages)
    ? ads.data!.pages.flatMap((p) =>
        Array.isArray(p?.data) ? p.data : [],
      )
    : [];

  const [adId, setAdId] = useState<string>('');
  const [tenantId, setTenantId] = useState<string>('');
  const [tenantName, setTenantName] = useState<string>('');
  const [tenantPhone, setTenantPhone] = useState<string>('');
  const [tenantEmail, setTenantEmail] = useState<string>('');
  const [start, setStart] = useState<string>('');
  const [months, setMonths] = useState<string>('12');
  const [rent, setRent] = useState<string>('');
  const [deposit, setDeposit] = useState<string>('');

  const reset = () => {
    setAdId('');
    setTenantId('');
    setTenantName('');
    setTenantPhone('');
    setTenantEmail('');
    setStart('');
    setMonths('12');
    setRent('');
    setDeposit('');
  };

  const onSubmit = () => {
    const duration = Number(months);
    if (
      !adId ||
      !tenantName.trim() ||
      !tenantPhone.trim() ||
      !start ||
      !Number.isInteger(duration) ||
      duration < 1 ||
      duration > 120
    ) {
      Alert.alert(
        'Champs manquants',
        'Annonce, nom + téléphone du locataire, date de début et durée (1–120 mois) requis.',
      );
      return;
    }
    generate.mutate(
      {
        adId,
        tenant_name: tenantName.trim(),
        tenant_phone: tenantPhone.trim(),
        tenant_email: tenantEmail.trim() || undefined,
        lease_start: start,
        lease_duration_months: duration,
        monthly_rent: rent ? Number(rent) : undefined,
        deposit_amount: deposit ? Number(deposit) : undefined,
      },
      {
        onSuccess: () => {
          onOpenChange(false);
          reset();
          Alert.alert('Succès', 'Bail généré.');
        },
        onError: (err) => Alert.alert('Erreur', extractApiErrorMessage(err)),
      },
    );
  };

  return (
    <Sheet modal open={open} onOpenChange={onOpenChange} snapPoints={[88]} dismissOnSnapToBottom>
      <Sheet.Overlay />
      <Sheet.Frame padding={20} gap={12}>
        <Sheet.Handle />
        <Paragraph fontSize={18} fontWeight="900">
          Générer un bail
        </Paragraph>

        <Sheet.ScrollView contentContainerStyle={{ gap: 14, paddingBottom: 20 }}>
          <YStack gap={6}>
            <Paragraph fontSize={12.5} fontWeight="700" color="$slate700">
              Annonce
            </Paragraph>
            <XStack gap={6} flexWrap="wrap">
              {flatAds.slice(0, 20).map((ad) => {
                const sel = adId === ad.id;
                return (
                  <Button
                    key={ad.id}
                    size="$2"
                    chromeless
                    borderRadius={999}
                    backgroundColor={sel ? '$brand' : '$slate100'}
                    onPress={() => setAdId(ad.id)}
                    paddingHorizontal={12}
                    maxWidth={220}
                  >
                    <Paragraph
                      fontSize={12}
                      fontWeight="700"
                      color={sel ? 'white' : '$slate700'}
                      numberOfLines={1}
                    >
                      {ad.title}
                    </Paragraph>
                  </Button>
                );
              })}
            </XStack>
          </YStack>

          <YStack gap={6}>
            <Paragraph fontSize={12.5} fontWeight="700" color="$slate700">
              Locataire (préremplit les champs)
            </Paragraph>
            <XStack gap={6} flexWrap="wrap">
              {(tenants.data ?? []).map((tn) => {
                const sel = tenantId === tn.id;
                return (
                  <Button
                    key={tn.id}
                    size="$2"
                    chromeless
                    borderRadius={999}
                    backgroundColor={sel ? '$brand' : '$slate100'}
                    onPress={() => {
                      setTenantId(tn.id);
                      setTenantName(tn.name);
                      setTenantPhone(tn.phone ?? '');
                      setTenantEmail(tn.email ?? '');
                    }}
                    paddingHorizontal={12}
                  >
                    <Paragraph fontSize={12} fontWeight="700" color={sel ? 'white' : '$slate700'}>
                      {tn.name}
                    </Paragraph>
                  </Button>
                );
              })}
            </XStack>
          </YStack>

          <YStack gap={6}>
            <Paragraph fontSize={12.5} fontWeight="700" color="$slate700">
              Nom du locataire
            </Paragraph>
            <Input value={tenantName} onChangeText={setTenantName} placeholder="ex. Marie Ngo Bell" autoCapitalize="words" />
          </YStack>

          <XStack gap={10}>
            <YStack flex={1} gap={6}>
              <Paragraph fontSize={12.5} fontWeight="700" color="$slate700">
                Téléphone
              </Paragraph>
              <Input value={tenantPhone} onChangeText={setTenantPhone} placeholder="+237…" keyboardType="phone-pad" />
            </YStack>
            <YStack flex={1} gap={6}>
              <Paragraph fontSize={12.5} fontWeight="700" color="$slate700">
                Email (optionnel)
              </Paragraph>
              <Input value={tenantEmail} onChangeText={setTenantEmail} placeholder="email@exemple.com" keyboardType="email-address" autoCapitalize="none" />
            </YStack>
          </XStack>

          <XStack gap={10}>
            <YStack flex={1} gap={6}>
              <Paragraph fontSize={12.5} fontWeight="700" color="$slate700">
                Début (AAAA-MM-JJ)
              </Paragraph>
              <Input value={start} onChangeText={setStart} placeholder="2026-08-01" />
            </YStack>
            <YStack flex={1} gap={6}>
              <Paragraph fontSize={12.5} fontWeight="700" color="$slate700">
                Durée (mois)
              </Paragraph>
              <Input value={months} onChangeText={setMonths} placeholder="12" keyboardType="numeric" />
            </YStack>
          </XStack>

          <XStack gap={10}>
            <YStack flex={1} gap={6}>
              <Paragraph fontSize={12.5} fontWeight="700" color="$slate700">
                Loyer mensuel
              </Paragraph>
              <Input value={rent} onChangeText={setRent} placeholder="150000" keyboardType="numeric" />
            </YStack>
            <YStack flex={1} gap={6}>
              <Paragraph fontSize={12.5} fontWeight="700" color="$slate700">
                Caution
              </Paragraph>
              <Input value={deposit} onChangeText={setDeposit} placeholder="300000" keyboardType="numeric" />
            </YStack>
          </XStack>
        </Sheet.ScrollView>

        <Button
          size="$4"
          backgroundColor="$brand"
          color="white"
          fontWeight="800"
          borderRadius={12}
          onPress={onSubmit}
          disabled={generate.isPending}
        >
          {generate.isPending ? 'Génération…' : 'Générer le bail'}
        </Button>
      </Sheet.Frame>
    </Sheet>
  );
}

function RenewLeaseSheet({
  lease,
  onClose,
}: {
  lease: LeaseContract | null;
  onClose: () => void;
}) {
  const renew = useRenewLease();
  const [months, setMonths] = useState<number>(12);

  const onSubmit = () => {
    if (!lease) return;
    renew.mutate(
      { id: lease.id, extend_months: months },
      {
        onSuccess: () => {
          setMonths(12);
          onClose();
          Alert.alert('Succès', `Bail prolongé de ${months} mois.`);
        },
        onError: (err) => Alert.alert('Erreur', extractApiErrorMessage(err)),
      },
    );
  };

  return (
    <Sheet
      modal
      open={lease !== null}
      onOpenChange={(o: boolean) => !o && onClose()}
      snapPoints={[40]}
      dismissOnSnapToBottom
    >
      <Sheet.Overlay />
      <Sheet.Frame padding={20} gap={12}>
        <Sheet.Handle />
        <Paragraph fontSize={18} fontWeight="900">
          Renouveler le bail
        </Paragraph>
        <Paragraph fontSize={12.5} color="$slate500">
          De combien de mois prolonger le bail ?
        </Paragraph>
        <XStack gap={8} flexWrap="wrap">
          {[3, 6, 12, 24].map((m) => (
            <Button
              key={m}
              size="$3"
              chromeless
              borderRadius={999}
              backgroundColor={months === m ? '$brand' : '$slate100'}
              color={months === m ? 'white' : '$slate700'}
              fontWeight="800"
              paddingHorizontal={16}
              onPress={() => setMonths(m)}
            >
              {m} mois
            </Button>
          ))}
        </XStack>
        <Button
          size="$4"
          backgroundColor="$brand"
          color="white"
          fontWeight="800"
          borderRadius={12}
          onPress={onSubmit}
          disabled={renew.isPending}
        >
          {renew.isPending ? 'Renouvellement…' : `Prolonger de ${months} mois`}
        </Button>
      </Sheet.Frame>
    </Sheet>
  );
}

function TerminateLeaseSheet({
  lease,
  onClose,
}: {
  lease: LeaseContract | null;
  onClose: () => void;
}) {
  const terminate = useTerminateLease();
  const [reason, setReason] = useState<string>('');

  const onSubmit = () => {
    if (!lease) return;
    if (reason.trim().length < 3) {
      Alert.alert('Motif requis', 'Indiquez un motif de résiliation (3 caractères minimum).');
      return;
    }
    terminate.mutate(
      { id: lease.id, reason: reason.trim() },
      {
        onSuccess: () => {
          setReason('');
          onClose();
          Alert.alert('Bail résilié', 'La résiliation a été enregistrée.');
        },
        onError: (err) => Alert.alert('Erreur', extractApiErrorMessage(err)),
      },
    );
  };

  return (
    <Sheet
      modal
      open={lease !== null}
      onOpenChange={(o: boolean) => !o && onClose()}
      snapPoints={[45]}
      dismissOnSnapToBottom
    >
      <Sheet.Overlay />
      <Sheet.Frame padding={20} gap={12}>
        <Sheet.Handle />
        <Paragraph fontSize={18} fontWeight="900" color={brand.danger}>
          Résilier le bail
        </Paragraph>
        <Paragraph fontSize={12.5} color="$slate500">
          Cette action est irréversible. Le motif est requis (traçabilité).
        </Paragraph>
        <Input
          value={reason}
          onChangeText={setReason}
          placeholder="Motif (ex. non-paiement)"
          multiline
        />
        <Button
          size="$4"
          backgroundColor={brand.danger}
          color="white"
          fontWeight="800"
          borderRadius={12}
          onPress={onSubmit}
          disabled={terminate.isPending}
        >
          {terminate.isPending ? 'Résiliation…' : 'Confirmer la résiliation'}
        </Button>
      </Sheet.Frame>
    </Sheet>
  );
}
