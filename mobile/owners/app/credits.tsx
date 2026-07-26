import { Coins, Sparkles, Wallet, Zap } from '@tamagui/lucide-icons';
import { useState } from 'react';
import { Alert, RefreshControl, ScrollView } from 'react-native';
import { Button, Paragraph, Spinner, XStack, YStack } from 'tamagui';

import { extractApiErrorMessage } from '@/api/extract-error';
import { useSession } from '@/auth/SessionProvider';
import { EmptyState } from '@/components/EmptyState';
import { FadeIn } from '@/components/FadeIn';
import { PaymentSheet } from '@/components/payments/PaymentSheet';
import { ScreenHeader } from '@/components/ScreenHeader';
import {
  useCreditPackages,
  useCreditsBalance,
  usePurchaseCredits,
  useVerifyCreditPurchase,
} from '@/hooks/useCredits';
import { buildCallbackUrl, openHostedCheckout } from '@/services/checkout';
import { brand } from '@/theme/tokens';
import { formatFcfa } from '@/utils/format';
import type { CreditPackage } from '@/types/credits';
import { useRouter } from 'expo-router';

/**
 * Boutique de crédits.
 *
 * UX :
 *  - Hero card avec le solde courant (live via `useCreditsBalance`)
 *  - Grille de packages → tap = directement initie le purchase
 *    (le backend renvoie un `payment_link` qu'on ouvre dans
 *    `WebBrowser.openAuthSessionAsync` via `openHostedCheckout`).
 *  - Pour les méthodes nécessitant un phone (mobile money), on
 *    bascule sur `PaymentSheet` afin de collecter le phone proprement.
 *
 * Note design : `purchaseCredits` ne traverse PAS par
 * `/payments/initiate_payment` mais par `/credits/purchase/{pkg}` —
 * c'est l'endpoint dédié backend qui gère les bonus_points, le
 * referral code etc. On garde le PaymentSheet comme fallback pour
 * forcer mobile money + plier dans le pattern unifié.
 */
export default function CreditsScreen() {
  const router = useRouter();
  const { isAuthenticated } = useSession();
  const balance = useCreditsBalance(isAuthenticated);
  const packages = useCreditPackages(isAuthenticated);
  const purchase = usePurchaseCredits();
  const verify = useVerifyCreditPurchase();

  const [showSheet, setShowSheet] = useState(false);
  const [selectedPkg, setSelectedPkg] = useState<CreditPackage | null>(null);

  const refresh = () => {
    balance.refetch();
    packages.refetch();
  };

  /**
   * Fast-path : on lance directement le purchase backend sans
   * passer par PaymentSheet pour les méthodes qui ne demandent pas
   * de phone (carte). Le backend redirige sur la page hosted, on
   * ouvre le browser, on récupère le tx_ref.
   */
  const handleQuickPurchase = async (pkg: CreditPackage) => {
    // Garde synchrone anti double-initiation : pendant qu'un achat est en
    // vol, un tap sur N'IMPORTE QUEL pack (pas seulement celui-ci) ne doit
    // pas créer un second tx_ref / une double charge potentielle.
    if (purchase.isPending) return;
    setSelectedPkg(pkg);
    try {
      const init = await purchase.mutateAsync({
        packageId: pkg.id,
        // Deep-link natif → le backend l'enveloppe dans le pont HTTPS pour que
        // l'onglet in-app se ferme tout seul en fin de paiement.
        callback_url: buildCallbackUrl(),
      });
      const link = init.payment_link ?? init.payment_url;
      if (!link) {
        Alert.alert('Erreur', 'Le gateway n’a pas renvoyé de lien.');
        return;
      }
      const result = await openHostedCheckout(link, init.tx_ref);
      if (result.cancelled) {
        // L'onglet peut avoir été fermé APRÈS la validation du push mobile
        // money : réconciliation silencieuse + proposition de suivre le
        // statut (l'écran de résultat tranche via le poll public-status).
        verify.mutate({ tx_ref: init.tx_ref });
        Alert.alert(
          'Paiement non finalisé',
          'Vous avez fermé la fenêtre de paiement. Si vous avez validé le paiement sur votre téléphone, il sera confirmé automatiquement.',
          [
            {
              text: 'Vérifier le statut',
              onPress: () =>
                router.push({ pathname: '/payment-success', params: { tx_ref: init.tx_ref } } as never),
            },
            { text: 'Fermer', style: 'cancel' },
          ],
        );
        return;
      }
      if (result.error) {
        Alert.alert('Erreur', result.error.message);
        return;
      }
      const finalRef = result.txRef ?? init.tx_ref;
      // verify-purchase opportuniste — invalide le balance dès qu'on rentre
      verify.mutate({ tx_ref: finalRef });
      router.push({ pathname: '/payment-success', params: { tx_ref: finalRef } } as never);
    } catch (err) {
      // Si l'erreur indique "method requires phone" → fallback PaymentSheet
      const msg = extractApiErrorMessage(err);
      if (/phone|téléphone|mobile money/i.test(msg)) {
        setShowSheet(true);
        return;
      }
      Alert.alert('Erreur', msg);
    }
  };

  return (
    <YStack flex={1} backgroundColor="$background">
      <ScreenHeader title="Crédits KeyHome" subtitle="Boostez vos annonces avec des crédits" />

      <ScrollView
        contentContainerStyle={{ padding: 16, paddingBottom: 32, gap: 14 }}
        refreshControl={
          <RefreshControl
            refreshing={balance.isRefetching || packages.isRefetching}
            onRefresh={refresh}
            tintColor={brand.primary}
          />
        }
      >
        {/* Hero balance */}
        <FadeIn>
          <YStack
            padding={20}
            gap={6}
            borderRadius={20}
            backgroundColor={brand.primary}
          >
            <XStack alignItems="center" gap={8}>
              <Wallet size={18} color="white" />
              <Paragraph fontSize={11.5} fontWeight="800" color="rgba(255,255,255,0.85)" letterSpacing={1}>
                SOLDE ACTUEL
              </Paragraph>
            </XStack>
            <XStack alignItems="baseline" gap={8}>
              <Paragraph fontSize={42} fontWeight="900" color="white">
                {balance.isLoading ? '…' : balance.data ?? 0}
              </Paragraph>
              <Paragraph fontSize={14} color="rgba(255,255,255,0.85)" fontWeight="700">
                crédits
              </Paragraph>
            </XStack>
            <Paragraph fontSize={12} color="rgba(255,255,255,0.8)">
              Utilisés pour booster une annonce, déverrouiller des contacts ou souscrire à un service pro.
            </Paragraph>
          </YStack>
        </FadeIn>

        {/* Packages */}
        <Paragraph fontSize={14} fontWeight="900" color="$slate900" marginTop={6}>
          Recharger
        </Paragraph>

        {packages.isLoading ? (
          <YStack height={260} alignItems="center" justifyContent="center">
            <Spinner color={brand.primary} size="large" />
          </YStack>
        ) : (packages.data ?? []).length === 0 ? (
          <YStack height={260}>
            <EmptyState
              icon={<Coins size={28} color={brand.primary} />}
              title="Aucun package disponible"
              hint="Revenez plus tard ou contactez le support."
            />
          </YStack>
        ) : (
          (packages.data ?? []).map((pkg, idx) => {
            const bonus = pkg.bonus_points ?? 0;
            const isPopular = pkg.is_popular ?? false;
            return (
              <FadeIn key={pkg.id} delay={idx * 60}>
                <YStack
                  padding={16}
                  gap={10}
                  borderRadius={16}
                  borderWidth={isPopular ? 2 : 1}
                  borderColor={isPopular ? brand.primary : '$slate300'}
                  backgroundColor={isPopular ? brand.primaryAlpha10 : '$background'}
                >
                  <XStack alignItems="center" gap={10}>
                    <YStack
                      width={44}
                      height={44}
                      borderRadius={22}
                      alignItems="center"
                      justifyContent="center"
                      backgroundColor={brand.primaryAlpha10}
                    >
                      {isPopular ? (
                        <Sparkles size={20} color={brand.accent} />
                      ) : (
                        <Zap size={20} color={brand.primary} />
                      )}
                    </YStack>
                    <YStack flex={1} gap={2}>
                      <Paragraph fontSize={15} fontWeight="900" color="$slate900">
                        {pkg.name}
                      </Paragraph>
                      <Paragraph fontSize={12.5} color="$slate500">
                        {pkg.points} crédits
                        {bonus > 0 ? (
                          <Paragraph color={brand.success} fontWeight="800">
                            {' '}
                            + {bonus} bonus
                          </Paragraph>
                        ) : null}
                      </Paragraph>
                    </YStack>
                    <YStack alignItems="flex-end" gap={2}>
                      <Paragraph fontSize={16} fontWeight="900" color="$slate900">
                        {formatFcfa(pkg.price)}
                      </Paragraph>
                      {isPopular ? (
                        <Paragraph fontSize={10} fontWeight="800" color={brand.accent}>
                          POPULAIRE
                        </Paragraph>
                      ) : null}
                    </YStack>
                  </XStack>

                  <Button
                    size="$3"
                    backgroundColor="$brand"
                    color="white"
                    fontWeight="800"
                    borderRadius={10}
                    disabled={purchase.isPending}
                    onPress={() => handleQuickPurchase(pkg)}
                  >
                    {purchase.isPending && selectedPkg?.id === pkg.id ? 'Connexion…' : 'Acheter'}
                  </Button>
                </YStack>
              </FadeIn>
            );
          })
        )}
      </ScrollView>

      {/* Fallback sheet : mobile money flow avec phone */}
      {selectedPkg ? (
        <PaymentSheet
          open={showSheet}
          onOpenChange={setShowSheet}
          title={selectedPkg.name}
          subtitle={`${selectedPkg.points} crédits${selectedPkg.bonus_points ? ` + ${selectedPkg.bonus_points} bonus` : ''}`}
          amount={selectedPkg.price}
          purpose="credit"
          extraPayload={{ reference_id: selectedPkg.id }}
          onSuccess={(txRef) => verify.mutate({ tx_ref: txRef })}
        />
      ) : null}
    </YStack>
  );
}
