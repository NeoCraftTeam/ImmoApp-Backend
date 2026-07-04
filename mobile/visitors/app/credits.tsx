import {
  ArrowLeft,
  CheckCircle2,
  Clock,
  CreditCard,
  Sparkles,
  Wallet,
  X,
  XCircle,
} from '@tamagui/lucide-icons';
import { formatDistanceToNow } from 'date-fns';
import { fr } from 'date-fns/locale';
import { Stack, useRouter } from 'expo-router';
import * as Linking from 'expo-linking';
import * as WebBrowser from 'expo-web-browser';
import { useMemo, useState } from 'react';
import { ActivityIndicator, Alert, FlatList, Modal, Pressable } from 'react-native';
import { H2, Paragraph, Spinner, XStack, YStack } from 'tamagui';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

import { extractApiErrorMessage } from '@/api/client';
import {
  useCreditPackages,
  usePurchaseCredits,
  useVerifyCreditPurchase,
  type CreditPackage,
} from '@/hooks/useCredits';
import { useCreditsBalance, usePayments } from '@/hooks/usePayments';
import { useCurrency } from '@/hooks/useCurrency';
import { useSession } from '@/auth/SessionProvider';
import { SUPPORTED_CURRENCIES } from '@/services/currency';
import { brand } from '@/theme/tokens';
import type { PaymentTransaction } from '@/types/payment';

/** Devises proposées en priorité dans le sélecteur cyclique. */
const QUICK_CURRENCIES = ['XAF', 'EUR', 'USD', 'XOF', 'GBP'].filter((c) =>
  SUPPORTED_CURRENCIES.includes(c),
);

function nextCurrency(current: string): string {
  const idx = QUICK_CURRENCIES.indexOf(current);
  return QUICK_CURRENCIES[(idx + 1) % QUICK_CURRENCIES.length] ?? 'XAF';
}

type Period = 'all' | '30d' | '90d';

const PERIODS: { key: Period; label: string; days: number | null }[] = [
  { key: 'all', label: 'Tout', days: null },
  { key: '30d', label: '30 jours', days: 30 },
  { key: '90d', label: '90 jours', days: 90 },
];

/**
 * Crédits + paiements. Carte de solde aux couleurs de la marque
 * (lisible en clair comme en sombre), achat de packs 100% natif
 * (checkout hébergé ouvert in-app puis vérifié au retour), historique
 * filtrable par période avec total dépensé.
 */
export default function CreditsScreen() {
  const router = useRouter();
  const insets = useSafeAreaInsets();
  const { isAuthenticated } = useSession();
  const balance = useCreditsBalance();
  const payments = usePayments();
  const [packsOpen, setPacksOpen] = useState(false);
  const [period, setPeriod] = useState<Period>('all');

  const filtered = useMemo(() => {
    const list = payments.data ?? [];
    const days = PERIODS.find((p) => p.key === period)?.days ?? null;
    if (days === null) return list;
    const cutoff = Date.now() - days * 24 * 60 * 60 * 1000;
    return list.filter((tx) => {
      const t = tx.created_at ? new Date(tx.created_at).getTime() : 0;
      return t >= cutoff;
    });
  }, [payments.data, period]);

  const totalSpent = useMemo(
    () =>
      filtered
        .filter((tx) => tx.status === 'success')
        .reduce((acc, tx) => acc + (tx.amount ?? 0), 0),
    [filtered],
  );

  if (!isAuthenticated) {
    return (
      <YStack flex={1} alignItems="center" justifyContent="center" padding="$5" gap={10} backgroundColor="$background">
        <Wallet size={36} color="$slate500" />
        <Paragraph fontSize={15} fontWeight="700" color="$slate900" textAlign="center">
          Connectez-vous pour voir vos crédits
        </Paragraph>
        <Pressable onPress={() => router.push('/(auth)/login')}>
          <XStack backgroundColor="$brand" paddingHorizontal={18} paddingVertical={10} borderRadius={10}>
            <Paragraph color="white" fontWeight="700">Se connecter</Paragraph>
          </XStack>
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
          borderBottomColor="$borderColor"
        >
          <Pressable onPress={() => router.back()} hitSlop={8} accessibilityRole="button" accessibilityLabel="Retour">
            <YStack width={36} height={36} borderRadius={18} backgroundColor="$slate100" alignItems="center" justifyContent="center">
              <ArrowLeft size={18} color="$slate700" />
            </YStack>
          </Pressable>
          <H2 fontSize={20} fontWeight="700" color="$slate900" flex={1}>
            Crédits
          </H2>
        </XStack>

        <FlatList
          data={filtered}
          keyExtractor={(item) => item.id}
          contentContainerStyle={{ paddingHorizontal: 16, paddingTop: 18, paddingBottom: insets.bottom + 24, gap: 10 }}
          ListHeaderComponent={
            <YStack gap={16} marginBottom={12}>
              {/* Carte de solde — couleur de marque, lisible clair & sombre */}
              <YStack padding={20} borderRadius={20} backgroundColor={brand.primary} gap={14} overflow="hidden">
                <XStack alignItems="center" gap={8}>
                  <Wallet size={16} color="rgba(255,255,255,0.9)" />
                  <Paragraph fontSize={13} color="rgba(255,255,255,0.9)" fontWeight="700">
                    Solde de crédits
                  </Paragraph>
                </XStack>
                <XStack alignItems="baseline" gap={8}>
                  <Paragraph fontSize={40} fontWeight="900" color="white" lineHeight={44}>
                    {balance.isLoading ? '—' : (balance.data ?? 0).toLocaleString('fr-FR')}
                  </Paragraph>
                  <Paragraph fontSize={16} fontWeight="700" color="rgba(255,255,255,0.85)">
                    crédits
                  </Paragraph>
                </XStack>
                <Pressable onPress={() => setPacksOpen(true)} hitSlop={6} accessibilityRole="button" accessibilityLabel="Recharger des crédits">
                  <XStack
                    backgroundColor="white"
                    paddingHorizontal={16}
                    paddingVertical={11}
                    borderRadius={12}
                    alignItems="center"
                    gap={8}
                    alignSelf="flex-start"
                  >
                    <CreditCard size={16} color={brand.primary} />
                    <Paragraph color={brand.primary} fontWeight="800">
                      Recharger
                    </Paragraph>
                  </XStack>
                </Pressable>
                <Paragraph fontSize={11.5} color="rgba(255,255,255,0.75)" lineHeight={16}>
                  Vos crédits débloquent l'accès aux coordonnées et au contact direct des bailleurs.
                </Paragraph>
              </YStack>

              <XStack alignItems="center" justifyContent="space-between">
                <Paragraph fontSize={15} fontWeight="700" color="$slate900">
                  Historique
                </Paragraph>
                {totalSpent > 0 ? (
                  <Paragraph fontSize={12} color="$slate500">
                    Total dépensé : {totalSpent.toLocaleString('fr-FR')} XAF
                  </Paragraph>
                ) : null}
              </XStack>

              <XStack gap={8}>
                {PERIODS.map((p) => {
                  const active = period === p.key;
                  return (
                    <Pressable key={p.key} onPress={() => setPeriod(p.key)} accessibilityRole="button">
                      <XStack
                        paddingHorizontal={14}
                        paddingVertical={7}
                        borderRadius={999}
                        backgroundColor={active ? '$brand' : '$slate100'}
                      >
                        <Paragraph fontSize={12.5} fontWeight="700" color={active ? '$brandText' : '$slate700'}>
                          {p.label}
                        </Paragraph>
                      </XStack>
                    </Pressable>
                  );
                })}
              </XStack>
            </YStack>
          }
          ListEmptyComponent={
            payments.isLoading ? (
              <YStack alignItems="center" padding={20}>
                <ActivityIndicator />
              </YStack>
            ) : payments.isError ? (
              <Paragraph color="$slate700">{extractApiErrorMessage(payments.error)}</Paragraph>
            ) : (
              <YStack padding={20} alignItems="center">
                <Paragraph fontSize={13} color="$slate500" textAlign="center">
                  Aucune transaction sur cette période.
                </Paragraph>
              </YStack>
            )
          }
          renderItem={({ item }) => <TxRow tx={item} />}
        />
      </YStack>

      <PacksModal
        open={packsOpen}
        onClose={() => setPacksOpen(false)}
        onDone={() => {
          setPacksOpen(false);
          balance.refetch();
          payments.refetch();
        }}
      />
    </>
  );
}

function PacksModal({
  open,
  onClose,
  onDone,
}: {
  open: boolean;
  onClose: () => void;
  onDone: () => void;
}) {
  const insets = useSafeAreaInsets();
  const packages = useCreditPackages(open);
  const purchase = usePurchaseCredits();
  const verify = useVerifyCreditPurchase();
  const { format, currency: displayCurrency, setCurrency } = useCurrency();
  const [busyId, setBusyId] = useState<string | null>(null);

  const buy = async (pkg: CreditPackage) => {
    if (busyId) return;
    setBusyId(pkg.id);
    try {
      const callbackUrl = Linking.createURL('credits/callback');
      const res = await purchase.mutateAsync({ packageId: pkg.id, callback_url: callbackUrl });
      const url = res.payment_url ?? res.payment_link;
      if (!url) {
        throw new Error('Lien de paiement indisponible.');
      }
      // Checkout hébergé ouvert IN-APP (pas de redirection vers le web
      // de l'app) ; au retour on vérifie la transaction pour créditer.
      await WebBrowser.openAuthSessionAsync(url, callbackUrl);
      await verify.mutateAsync({ tx_ref: res.tx_ref }).catch(() => {});
      onDone();
      Alert.alert('Paiement', 'Si votre paiement est confirmé, vos crédits seront ajoutés sous peu.');
    } catch (err) {
      Alert.alert('Erreur', extractApiErrorMessage(err));
    } finally {
      setBusyId(null);
    }
  };

  return (
    <Modal visible={open} transparent animationType="slide" onRequestClose={onClose}>
      <YStack flex={1} justifyContent="flex-end" backgroundColor="rgba(0,0,0,0.45)">
        <Pressable style={{ flex: 1 }} onPress={onClose} />
        <YStack
          backgroundColor="$background"
          borderTopLeftRadius={24}
          borderTopRightRadius={24}
          paddingHorizontal={20}
          paddingTop={16}
          paddingBottom={insets.bottom + 20}
          gap={14}
          maxHeight="82%"
        >
          <XStack alignItems="center" justifyContent="space-between">
            <XStack alignItems="center" gap={8} flex={1}>
              <Sparkles size={20} color={brand.primary} />
              <H2 fontSize={18} fontWeight="800" color="$slate900">
                Recharger
              </H2>
            </XStack>
            {/* Sélecteur de devise (défile les devises courantes). */}
            <Pressable
              onPress={() => setCurrency(nextCurrency(displayCurrency))}
              hitSlop={6}
              accessibilityRole="button"
              accessibilityLabel="Changer la devise d'affichage"
            >
              <XStack
                alignItems="center"
                gap={4}
                paddingHorizontal={12}
                height={32}
                borderRadius={16}
                backgroundColor="$slate100"
                marginRight={8}
              >
                <Paragraph fontSize={13} fontWeight="800" color="$slate900">
                  {displayCurrency}
                </Paragraph>
              </XStack>
            </Pressable>
            <Pressable onPress={onClose} hitSlop={8} accessibilityRole="button" accessibilityLabel="Fermer">
              <YStack width={32} height={32} borderRadius={16} backgroundColor="$slate100" alignItems="center" justifyContent="center">
                <X size={16} color="$slate700" />
              </YStack>
            </Pressable>
          </XStack>

          {packages.isLoading ? (
            <YStack padding={24} alignItems="center">
              <ActivityIndicator />
            </YStack>
          ) : (packages.data?.length ?? 0) === 0 ? (
            <Paragraph fontSize={13} color="$slate500" padding={12} textAlign="center">
              Aucun pack disponible pour le moment.
            </Paragraph>
          ) : (
            <FlatList
              data={packages.data ?? []}
              keyExtractor={(p) => p.id}
              contentContainerStyle={{ gap: 10, paddingBottom: 8 }}
              renderItem={({ item }) => {
                const credits = item.points_awarded ?? 0;
                const busy = busyId === item.id;
                return (
                  <Pressable onPress={() => void buy(item)} disabled={Boolean(busyId)} accessibilityRole="button">
                    <XStack
                      alignItems="center"
                      gap={12}
                      padding={16}
                      borderRadius={16}
                      borderWidth={1.5}
                      borderColor={item.is_popular ? '$brand' : '$borderColor'}
                      backgroundColor={item.is_popular ? '$brandAlpha10' : '$background'}
                    >
                      <YStack flex={1} gap={2}>
                        <XStack alignItems="center" gap={8}>
                          <Paragraph fontSize={16} fontWeight="800" color="$slate900">
                            {item.name}
                          </Paragraph>
                          {item.is_popular ? (
                            <XStack backgroundColor="$brand" paddingHorizontal={7} paddingVertical={2} borderRadius={999}>
                              <Paragraph fontSize={10} fontWeight="800" color="$brandText">
                                POPULAIRE
                              </Paragraph>
                            </XStack>
                          ) : null}
                        </XStack>
                        <Paragraph fontSize={13} color="$slate500">
                          {credits.toLocaleString('fr-FR')} crédits
                        </Paragraph>
                      </YStack>
                      {busy ? (
                        <Spinner color={brand.primary} />
                      ) : (
                        <YStack alignItems="flex-end">
                          <Paragraph fontSize={15} fontWeight="800" color={brand.primary}>
                            {format(item.price)}
                          </Paragraph>
                          {displayCurrency !== 'XAF' && displayCurrency !== 'XOF' ? (
                            <Paragraph fontSize={11} color="$slate500">
                              ≈ {item.price.toLocaleString('fr-FR')} FCFA
                            </Paragraph>
                          ) : null}
                        </YStack>
                      )}
                    </XStack>
                  </Pressable>
                );
              }}
            />
          )}
        </YStack>
      </YStack>
    </Modal>
  );
}

function TxRow({ tx }: { tx: PaymentTransaction }) {
  const status = statusFor(tx.status);
  const relative = (() => {
    try {
      return tx.created_at
        ? formatDistanceToNow(new Date(tx.created_at), { addSuffix: true, locale: fr })
        : '';
    } catch {
      return '';
    }
  })();

  return (
    <XStack
      padding={14}
      borderRadius={12}
      borderWidth={1}
      borderColor="$borderColor"
      backgroundColor="$background"
      alignItems="center"
      gap={12}
    >
      <YStack width={36} height={36} borderRadius={18} backgroundColor={`${status.color}20`} alignItems="center" justifyContent="center">
        {status.icon}
      </YStack>
      <YStack flex={1} gap={2}>
        <Paragraph fontSize={14} fontWeight="700" color="$slate900" numberOfLines={1}>
          {tx.description ?? 'Transaction'}
        </Paragraph>
        <Paragraph fontSize={12} color="$slate500">
          {relative} · {tx.provider ?? 'KeyHome'}
        </Paragraph>
      </YStack>
      <YStack alignItems="flex-end" gap={2}>
        <Paragraph fontSize={14} fontWeight="800" color="$slate900">
          {tx.amount.toLocaleString('fr-FR')} {tx.currency ?? 'XAF'}
        </Paragraph>
        <Paragraph fontSize={11} fontWeight="700" color={status.color}>
          {status.label}
        </Paragraph>
      </YStack>
    </XStack>
  );
}

function statusFor(s: string): { icon: React.ReactNode; color: string; label: string } {
  if (s === 'success') {
    return { icon: <CheckCircle2 size={18} color={brand.success} />, color: brand.success, label: 'Réussi' };
  }
  if (s === 'failed') {
    return { icon: <XCircle size={18} color={brand.danger} />, color: brand.danger, label: 'Échoué' };
  }
  if (s === 'refunded') {
    return { icon: <CheckCircle2 size={18} color={brand.info} />, color: brand.info, label: 'Remboursé' };
  }
  return { icon: <Clock size={18} color={brand.warning} />, color: brand.warning, label: 'En attente' };
}
