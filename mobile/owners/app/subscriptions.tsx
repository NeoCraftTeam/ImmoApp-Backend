import { Check, Crown, Infinity as InfinityIcon, Sparkles } from '@tamagui/lucide-icons';
import { useState } from 'react';
import { Alert, RefreshControl, ScrollView } from 'react-native';
import { Button, Paragraph, Spinner, XStack, YStack } from 'tamagui';

import { extractApiErrorMessage } from '@/api/extract-error';
import { useSession } from '@/auth/SessionProvider';
import { PaymentSheet } from '@/components/payments/PaymentSheet';
import { ScreenHeader } from '@/components/ScreenHeader';
import {
  useCancelSubscription,
  useCurrentSubscription,
  useSubscriptionPlans,
  useToggleAutoRenew,
} from '@/hooks/useSubscriptions';
import { brand } from '@/theme/tokens';
import { formatDate, formatFcfa } from '@/utils/format';
import { t } from '@/i18n';
import type { CurrentSubscription, SubscriptionPlan } from '@/types/owner';

type BillingPeriod = 'monthly' | 'yearly';

const PERIODS: { value: BillingPeriod; key: string }[] = [
  { value: 'monthly', key: 'subscription.monthly' },
  { value: 'yearly', key: 'subscription.yearly' },
];

export default function SubscriptionsScreen() {
  const { isAuthenticated } = useSession();
  const [period, setPeriod] = useState<BillingPeriod>('monthly');
  const [paying, setPaying] = useState<SubscriptionPlan | null>(null);

  const current = useCurrentSubscription(isAuthenticated);
  const plans = useSubscriptionPlans();
  const cancel = useCancelSubscription();
  const toggleAutoRenew = useToggleAutoRenew();

  const isLoading = current.isLoading || plans.isLoading;
  const active = current.data?.has_subscription ? current.data.subscription : null;

  const onRefresh = () => {
    current.refetch();
    plans.refetch();
  };

  const handleSubscribe = (plan: SubscriptionPlan) => {
    setPaying(plan);
  };

  const payingPrice =
    paying ? (period === 'monthly' ? paying.price_monthly : paying.price_yearly) : 0;

  const handleCancel = () => {
    Alert.alert(
      t('subscription.cancel'),
      'Voulez-vous vraiment annuler votre abonnement ?',
      [
        { text: t('common.cancel'), style: 'cancel' },
        {
          text: t('common.confirm'),
          style: 'destructive',
          onPress: () =>
            cancel.mutate(undefined, {
              onError: (err) => Alert.alert(t('common.error'), extractApiErrorMessage(err)),
            }),
        },
      ],
    );
  };

  return (
    <YStack flex={1} backgroundColor="$background">
      <ScreenHeader title={t('subscription.title')} />

      {isLoading ? (
        <YStack flex={1} alignItems="center" justifyContent="center">
          <Spinner color={brand.primary} size="large" />
        </YStack>
      ) : (
        <ScrollView
          contentContainerStyle={{ padding: 16, paddingBottom: 32, gap: 16 }}
          refreshControl={
            <RefreshControl
              refreshing={current.isRefetching || plans.isRefetching}
              onRefresh={onRefresh}
              tintColor={brand.primary}
            />
          }
          showsVerticalScrollIndicator={false}
        >
          {active ? (
            <ActiveSubscriptionCard
              subscription={active}
              busy={toggleAutoRenew.isPending || cancel.isPending}
              onToggleAutoRenew={(next) =>
                toggleAutoRenew.mutate(next, {
                  onError: (err) =>
                    Alert.alert(t('common.error'), extractApiErrorMessage(err)),
                })
              }
              onCancel={handleCancel}
            />
          ) : null}

          {/* Period toggle */}
          <YStack gap={10}>
            <Paragraph fontSize={16} fontWeight="800" color="$slate900">
              {t('subscription.choosePlan')}
            </Paragraph>
            <XStack
              backgroundColor="$slate100"
              borderRadius={999}
              padding={4}
              gap={4}
            >
              {PERIODS.map((p) => {
                const isActive = period === p.value;
                return (
                  <Button
                    key={p.value}
                    flex={1}
                    size="$3"
                    chromeless
                    borderRadius={999}
                    backgroundColor={isActive ? '$brand' : 'transparent'}
                    onPress={() => setPeriod(p.value)}
                    pressStyle={{ opacity: 0.85 }}
                  >
                    <Paragraph
                      fontSize={13.5}
                      fontWeight="800"
                      color={isActive ? 'white' : '$slate700'}
                    >
                      {t(p.key)}
                    </Paragraph>
                  </Button>
                );
              })}
            </XStack>
          </YStack>

          {/* Plans */}
          <YStack gap={14}>
            {(plans.data ?? []).map((plan, index) => (
              <PlanCard
                key={plan.id}
                plan={plan}
                period={period}
                popular={index === 1}
                currentPlanId={active?.plan?.id}
                busy={false}
                onSubscribe={() => handleSubscribe(plan)}
              />
            ))}
          </YStack>
        </ScrollView>
      )}

      {/* PaymentSheet — unified gateway flow */}
      {paying ? (
        <PaymentSheet
          open={paying !== null}
          onOpenChange={(o) => !o && setPaying(null)}
          title={paying.name}
          subtitle={
            period === 'monthly'
              ? t('subscription.monthly')
              : t('subscription.yearly')
          }
          amount={payingPrice}
          purpose="subscription"
          extraPayload={{
            plan_id: paying.id,
            billing_period: period,
            reference_id: paying.id,
          }}
        />
      ) : null}
    </YStack>
  );
}

function ActiveSubscriptionCard({
  subscription,
  busy,
  onToggleAutoRenew,
  onCancel,
}: {
  subscription: NonNullable<CurrentSubscription['subscription']>;
  busy: boolean;
  onToggleAutoRenew: (next: boolean) => void;
  onCancel: () => void;
}) {
  const autoRenew = subscription.auto_renew ?? false;

  return (
    <YStack
      borderWidth={1.5}
      borderColor="$brand"
      borderRadius={18}
      padding={16}
      gap={12}
      backgroundColor={brand.primaryAlpha10}
    >
      <XStack alignItems="center" justifyContent="space-between">
        <XStack alignItems="center" gap={8}>
          <Crown size={18} color={brand.primary} />
          <Paragraph fontSize={11} fontWeight="800" color="$brand" letterSpacing={0.5}>
            {t('subscription.currentPlan').toUpperCase()}
          </Paragraph>
        </XStack>
        {subscription.status_label || subscription.status ? (
          <XStack
            backgroundColor={`${brand.success}1A`}
            paddingHorizontal={10}
            paddingVertical={4}
            borderRadius={999}
          >
            <Paragraph fontSize={11} fontWeight="800" color={brand.success}>
              {subscription.status_label ?? subscription.status}
            </Paragraph>
          </XStack>
        ) : null}
      </XStack>

      <Paragraph fontSize={20} fontWeight="900" color="$slate900">
        {subscription.plan?.name ?? '—'}
      </Paragraph>

      <XStack gap={16} flexWrap="wrap">
        {subscription.expires_at ? (
          <YStack gap={2}>
            <Paragraph fontSize={11} color="$slate500" fontWeight="600">
              {t('subscription.expiresOn')}
            </Paragraph>
            <Paragraph fontSize={13.5} fontWeight="800" color="$slate900">
              {formatDate(subscription.expires_at)}
            </Paragraph>
          </YStack>
        ) : null}
        {subscription.days_remaining !== undefined ? (
          <YStack gap={2}>
            <Paragraph fontSize={11} color="$slate500" fontWeight="600">
              {t('subscription.daysLeft')}
            </Paragraph>
            <Paragraph fontSize={13.5} fontWeight="800" color="$slate900">
              {subscription.days_remaining}
            </Paragraph>
          </YStack>
        ) : null}
      </XStack>

      {/* Auto-renew toggle */}
      <XStack
        alignItems="center"
        justifyContent="space-between"
        paddingVertical={6}
        gap={10}
      >
        <Paragraph fontSize={13.5} fontWeight="700" color="$slate700" flex={1}>
          {t('subscription.autoRenew')}
        </Paragraph>
        <Button
          size="$2"
          disabled={busy}
          borderRadius={999}
          width={56}
          height={30}
          padding={0}
          backgroundColor={autoRenew ? '$brand' : '$slate300'}
          onPress={() => onToggleAutoRenew(!autoRenew)}
          pressStyle={{ opacity: 0.85 }}
        >
          <YStack
            width={24}
            height={24}
            borderRadius={12}
            backgroundColor="white"
            marginLeft={autoRenew ? 24 : -24}
          />
        </Button>
      </XStack>

      <Button
        size="$3"
        chromeless
        borderWidth={1}
        borderColor="$danger"
        borderRadius={10}
        disabled={busy}
        onPress={onCancel}
      >
        <Paragraph fontSize={13.5} fontWeight="700" color="$danger">
          {t('subscription.cancel')}
        </Paragraph>
      </Button>
    </YStack>
  );
}

function PlanCard({
  plan,
  period,
  popular,
  currentPlanId,
  busy,
  onSubscribe,
}: {
  plan: SubscriptionPlan;
  period: BillingPeriod;
  popular: boolean;
  currentPlanId?: string;
  busy: boolean;
  onSubscribe: () => void;
}) {
  const price = period === 'monthly' ? plan.price_monthly : plan.price_yearly;
  const isCurrent = currentPlanId === plan.id;

  return (
    <YStack
      borderWidth={popular ? 1.5 : 1}
      borderColor={popular ? '$brand' : '$slate300'}
      borderRadius={18}
      padding={16}
      gap={12}
      backgroundColor="$background"
    >
      <XStack alignItems="center" justifyContent="space-between" gap={8}>
        <Paragraph fontSize={17} fontWeight="900" color="$slate900" flex={1} numberOfLines={1}>
          {plan.name}
        </Paragraph>
        {popular ? (
          <XStack
            backgroundColor={brand.accentAlpha10}
            paddingHorizontal={10}
            paddingVertical={4}
            borderRadius={999}
            alignItems="center"
            gap={4}
          >
            <Sparkles size={12} color={brand.accentDark} />
            <Paragraph fontSize={10.5} fontWeight="800" color={brand.accentDark}>
              POPULAIRE
            </Paragraph>
          </XStack>
        ) : null}
      </XStack>

      {plan.description ? (
        <Paragraph fontSize={13} color="$slate500" lineHeight={19}>
          {plan.description}
        </Paragraph>
      ) : null}

      <XStack alignItems="flex-end" gap={6}>
        <Paragraph fontSize={26} fontWeight="900" color="$brand" letterSpacing={-0.5}>
          {formatFcfa(price)}
        </Paragraph>
        <Paragraph fontSize={12.5} color="$slate500" fontWeight="600" marginBottom={4}>
          /{period === 'monthly' ? t('subscription.monthly').toLowerCase() : t('subscription.yearly').toLowerCase()}
        </Paragraph>
      </XStack>

      {period === 'yearly' && plan.yearly_savings ? (
        <XStack
          alignSelf="flex-start"
          backgroundColor={`${brand.success}1A`}
          paddingHorizontal={10}
          paddingVertical={4}
          borderRadius={999}
        >
          <Paragraph fontSize={11.5} fontWeight="800" color={brand.success}>
            {t('subscription.savings')} {formatFcfa(plan.yearly_savings)}
          </Paragraph>
        </XStack>
      ) : null}

      {/* Capacity */}
      <XStack alignItems="center" gap={8}>
        {plan.is_unlimited ? (
          <InfinityIcon size={16} color={brand.primary} />
        ) : (
          <Check size={16} color={brand.primary} />
        )}
        <Paragraph fontSize={13} fontWeight="700" color="$slate700">
          {plan.is_unlimited
            ? t('subscription.unlimited')
            : `${plan.max_ads ?? 0} ${t('subscription.maxAds')}`}
        </Paragraph>
      </XStack>

      {/* Features */}
      {plan.features && plan.features.length > 0 ? (
        <YStack gap={8}>
          {plan.features.map((feature, i) => (
            <XStack key={`${plan.id}-feat-${i}`} alignItems="flex-start" gap={8}>
              <Check size={15} color={brand.success} />
              <Paragraph fontSize={13} color="$slate700" flex={1} lineHeight={19}>
                {feature}
              </Paragraph>
            </XStack>
          ))}
        </YStack>
      ) : null}

      <Button
        size="$4"
        backgroundColor={isCurrent ? '$slate300' : '$brand'}
        color="white"
        fontWeight="800"
        borderRadius={12}
        disabled={busy || isCurrent}
        onPress={onSubscribe}
      >
        {isCurrent ? t('subscription.currentPlan') : t('subscription.subscribe')}
      </Button>
    </YStack>
  );
}
