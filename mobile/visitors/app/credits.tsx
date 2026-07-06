import {
  ArrowLeft,
  CheckCircle2,
  ChevronRight,
  Clock,
  CreditCard,
  Smartphone,
  Sparkles,
  Wallet,
  X,
  XCircle,
} from '@tamagui/lucide-icons';
import type { ComponentType } from 'react';
import { formatDistanceToNow } from 'date-fns';
import { fr } from 'date-fns/locale';
import { Stack, useRouter } from 'expo-router';
import * as Linking from 'expo-linking';
import * as WebBrowser from 'expo-web-browser';
import { useMemo, useState } from 'react';
import { ActivityIndicator, Alert, FlatList, Modal, Pressable } from 'react-native';
import { H2, Paragraph, Spinner, XStack, YStack } from 'tamagui';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

import { apiClient, extractApiErrorMessage } from '@/api/client';
import { ENDPOINTS } from '@/api/endpoints';
import {
  useCreditPackages,
  usePurchaseCredits,
  useVerifyCreditPurchase,
  type CreditPackage,
} from '@/hooks/useCredits';
import { useCreditsBalance, usePayments } from '@/hooks/usePayments';
import { useSession } from '@/auth/SessionProvider';
import { brand } from '@/theme/tokens';
import type { PaymentTransaction } from '@/types/payment';

type PaymentOutcome = 'completed' | 'failed' | 'pending';

type PaymentMethodChoice = 'mobile_money' | 'orange_money' | 'card';

/** Moyens de paiement proposés (mobile money d'abord, puis carte). */
const PAYMENT_METHODS: {
  key: PaymentMethodChoice;
  label: string;
  sub: string;
  Icon: ComponentType<{ size?: number; color?: string }>;
}[] = [
  { key: 'mobile_money', label: 'MTN Mobile Money', sub: 'MoMo', Icon: Smartphone },
  { key: 'orange_money', label: 'Orange Money', sub: 'OM', Icon: Smartphone },
  { key: 'card', label: 'Carte bancaire', sub: 'Visa · Mastercard', Icon: CreditCard },
];

/**
 * Confirme un achat en RÉCONCILIANT ACTIVEMENT avec la passerelle via
 * POST /credits/verify-purchase (syncPaymentStatus re-query GeniusPay/Stripe).
 * Contrairement au webhook, ça fonctionne même quand le webhook ne peut
 * pas joindre le backend (local/sandbox). Codes : 200 = completed,
 * 202 = pending, 422 = failed. Boucle ~90 s, arrêt sur état terminal.
 */
async function pollVerifyPurchase(
  txRef: string,
  { attempts = 36, intervalMs = 2500 }: { attempts?: number; intervalMs?: number } = {},
): Promise<PaymentOutcome> {
  for (let i = 0; i < attempts; i++) {
    try {
      const { data } = await apiClient.post<{ status?: string }>(
        ENDPOINTS.credits.verifyPurchase,
        { tx_ref: txRef },
      );
      if (data?.status === 'completed') {
        return 'completed';
      }
      if (data?.status === 'failed') {
        return 'failed';
      }
      // 'pending' / 'not_found' → on retente.
    } catch (err) {
      // 422 = paiement échoué (verify renvoie status:failed en erreur).
      const status = (err as { response?: { data?: { status?: string } } })?.response?.data?.status;
      if (status === 'failed') {
        return 'failed';
      }
      /* autres erreurs transitoires → on retente */
    }
    await new Promise((r) => setTimeout(r, intervalMs));
  }
  return 'pending';
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
  const verify = useVerifyCreditPurchase();
  const [packsOpen, setPacksOpen] = useState(false);
  const [period, setPeriod] = useState<Period>('all');
  // tx_ref en cours de re-vérification manuelle (tap sur une ligne « En attente »).
  const [verifyingRef, setVerifyingRef] = useState<string | null>(null);

  const verifyPending = async (txRef: string) => {
    if (verifyingRef) return;
    setVerifyingRef(txRef);
    try {
      const res = await verify.mutateAsync({ tx_ref: txRef });
      await Promise.all([balance.refetch(), payments.refetch()]);
      if (res.status === 'completed') {
        Alert.alert('Paiement confirmé', 'Vos crédits ont été ajoutés à votre solde.');
      } else {
        // 202 → toujours en attente côté passerelle.
        Alert.alert('Toujours en attente', 'La passerelle n’a pas encore confirmé ce paiement. Réessayez dans un instant.');
      }
    } catch (err) {
      // 422 = paiement échoué (verify renvoie status:failed en erreur).
      await payments.refetch();
      const status = (err as { response?: { data?: { status?: string } } })?.response?.data?.status;
      if (status === 'failed') {
        Alert.alert('Paiement échoué', 'Cette transaction a été refusée ou annulée. Aucun crédit n’a été débité.');
      } else {
        Alert.alert('Erreur', extractApiErrorMessage(err));
      }
    } finally {
      setVerifyingRef(null);
    }
  };

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
          renderItem={({ item }) => (
            <TxRow
              tx={item}
              onVerify={verifyPending}
              verifying={verifyingRef !== null && verifyingRef === item.reference}
              disabled={verifyingRef !== null}
            />
          )}
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
  const [busyId, setBusyId] = useState<string | null>(null);
  // Pack en attente du choix du moyen de paiement.
  const [pendingPack, setPendingPack] = useState<CreditPackage | null>(null);

  const buy = async (pkg: CreditPackage, method: PaymentMethodChoice) => {
    if (busyId) return;
    setPendingPack(null);
    setBusyId(pkg.id);
    try {
      const callbackUrl = Linking.createURL('credits/callback');
      const res = await purchase.mutateAsync({
        packageId: pkg.id,
        callback_url: callbackUrl,
        payment_method: method,
      });
      const url = res.payment_url ?? res.payment_link;
      if (!url || !res.tx_ref) {
        throw new Error('Lien de paiement indisponible.');
      }

      // Checkout hébergé ouvert IN-APP (ASWebAuthenticationSession). La
      // passerelle redirige en fin de paiement vers un pont HTTPS backend qui
      // renvoie un 302 vers `callbackUrl` (deep-link natif) → l'onglet se ferme
      // TOUT SEUL et rend la main à l'app. `type: 'success'` = redirigé,
      // `type: 'cancel'` = l'utilisateur a fermé l'onglet. Dans les deux cas on
      // réconcilie activement l'état réel (verify-purchase) — le deep-link
      // prouve le retour, pas le succès. Marche même sans webhook (local/sandbox).
      // `preferEphemeralSession` : session isolée (pas de cookies partagés avec
      // Safari) → iOS n'affiche PAS le prompt de consentement « … souhaite
      // utiliser … pour se connecter ». Inutile pour un paiement.
      const result = await WebBrowser.openAuthSessionAsync(url, callbackUrl, {
        preferEphemeralSession: true,
      });
      // Réconciliation COURTE (~12 s max) pour ne pas bloquer l'UI : la
      // confirmation passerelle peut tarder (webhook/sandbox). Si toujours en
      // attente au bout de ce délai, on ferme la modale — la transaction
      // apparaît « En attente » dans l'historique et reste tapable pour
      // re-vérifier plus tard. Sur annulation, un contrôle très bref suffit.
      const outcome = await pollVerifyPurchase(
        res.tx_ref,
        result.type === 'success' ? { attempts: 8, intervalMs: 1500 } : { attempts: 3, intervalMs: 1500 },
      );

      if (outcome === 'completed') {
        // verify-purchase a déjà crédité côté serveur ; on rafraîchit le solde.
        await verify.mutateAsync({ tx_ref: res.tx_ref }).catch(() => {});
        onDone();
        Alert.alert('Paiement réussi', 'Vos crédits ont été ajoutés à votre solde.');
      } else if (outcome === 'failed') {
        onDone();
        Alert.alert('Paiement échoué', 'La transaction a été refusée ou annulée. Aucun crédit n’a été débité.');
      } else {
        // Toujours en attente : on ferme et on rafraîchit l'historique pour que
        // la ligne « En attente » (tapable) apparaisse immédiatement.
        onDone();
        Alert.alert(
          'Paiement en cours',
          'Nous confirmons votre paiement. Il apparaît dans l’historique — touchez-le pour vérifier son statut, vos crédits s’ajouteront dès validation.',
        );
      }
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
            <Pressable onPress={onClose} hitSlop={8} accessibilityRole="button" accessibilityLabel="Fermer">
              <YStack width={32} height={32} borderRadius={16} backgroundColor="$slate100" alignItems="center" justifyContent="center">
                <X size={16} color="$slate700" />
              </YStack>
            </Pressable>
          </XStack>

          {pendingPack ? (
            <YStack gap={12}>
              <XStack alignItems="center" gap={10}>
                <Pressable onPress={() => setPendingPack(null)} hitSlop={8} accessibilityLabel="Retour aux packs">
                  <YStack width={32} height={32} borderRadius={16} backgroundColor="$slate100" alignItems="center" justifyContent="center">
                    <ArrowLeft size={16} color="$slate700" />
                  </YStack>
                </Pressable>
                <YStack flex={1}>
                  <Paragraph fontSize={15} fontWeight="800" color="$slate900">
                    {pendingPack.name} · {(pendingPack.points_awarded ?? 0).toLocaleString('fr-FR')} crédits
                  </Paragraph>
                  <Paragraph fontSize={13} color="$slate500">
                    {pendingPack.price.toLocaleString('fr-FR')} FCFA · choisissez le moyen de paiement
                  </Paragraph>
                </YStack>
              </XStack>
              {PAYMENT_METHODS.map((m) => (
                <Pressable
                  key={m.key}
                  onPress={() => void buy(pendingPack, m.key)}
                  disabled={Boolean(busyId)}
                  accessibilityRole="button"
                >
                  <XStack
                    alignItems="center"
                    gap={12}
                    padding={16}
                    borderRadius={16}
                    borderWidth={1.5}
                    borderColor="$borderColor"
                    backgroundColor="$background"
                  >
                    <YStack width={40} height={40} borderRadius={20} backgroundColor="$slate100" alignItems="center" justifyContent="center">
                      <m.Icon size={20} color={brand.primary} />
                    </YStack>
                    <YStack flex={1}>
                      <Paragraph fontSize={15} fontWeight="800" color="$slate900">
                        {m.label}
                      </Paragraph>
                      <Paragraph fontSize={12.5} color="$slate500">
                        {m.sub}
                      </Paragraph>
                    </YStack>
                    {busyId === pendingPack.id ? (
                      <Spinner color={brand.primary} />
                    ) : (
                      <ChevronRight size={18} color="$slate400" />
                    )}
                  </XStack>
                </Pressable>
              ))}
            </YStack>
          ) : packages.isLoading ? (
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
                  <Pressable onPress={() => setPendingPack(item)} disabled={Boolean(busyId)} accessibilityRole="button">
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
                        <Paragraph fontSize={15} fontWeight="800" color={brand.primary}>
                          {item.price.toLocaleString('fr-FR')} FCFA
                        </Paragraph>
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

function TxRow({
  tx,
  onVerify,
  verifying,
  disabled,
}: {
  tx: PaymentTransaction;
  onVerify: (txRef: string) => void;
  verifying: boolean;
  disabled: boolean;
}) {
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

  // Une transaction en attente avec une référence KH peut être re-vérifiée
  // manuellement (tap) — utile si le callback n'a pas confirmé le paiement.
  const canVerify = tx.status === 'pending' && typeof tx.reference === 'string' && tx.reference !== '';

  const body = (
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
        {verifying ? <Spinner color={status.color} /> : status.icon}
      </YStack>
      <YStack flex={1} gap={2}>
        <Paragraph fontSize={14} fontWeight="700" color="$slate900" numberOfLines={1}>
          {tx.description ?? 'Transaction'}
        </Paragraph>
        <Paragraph fontSize={12} color="$slate500">
          {relative} · {tx.provider ?? 'KeyHome'}
        </Paragraph>
        {canVerify ? (
          <Paragraph fontSize={11.5} fontWeight="700" color={brand.primary}>
            {verifying ? 'Vérification…' : 'Toucher pour vérifier le statut'}
          </Paragraph>
        ) : null}
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

  if (!canVerify) {
    return body;
  }

  return (
    <Pressable
      onPress={() => onVerify(tx.reference as string)}
      disabled={disabled}
      accessibilityRole="button"
      accessibilityLabel="Vérifier le statut du paiement"
    >
      {body}
    </Pressable>
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
