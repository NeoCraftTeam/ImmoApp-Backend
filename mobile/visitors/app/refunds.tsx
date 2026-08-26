import { ArrowLeft, CheckCircle2, Clock, Plus, XCircle } from '@tamagui/lucide-icons';
import { formatDistanceToNow } from 'date-fns';
import { fr } from 'date-fns/locale';
import { Stack, useRouter, usePathname } from 'expo-router';
import { useState } from 'react';
import { ActivityIndicator, Alert, FlatList, Modal, Pressable, ScrollView, TextInput } from 'react-native';
import { Button, Paragraph, XStack, YStack } from 'tamagui';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

import { extractApiErrorMessage } from '@/api/client';
import { usePayments } from '@/hooks/usePayments';
import { useRefunds, useRequestRefund } from '@/hooks/useRefunds';
import { rememberPendingRoute } from '@/auth/pending-route';
import { useSession } from '@/auth/SessionProvider';
import { brand } from '@/theme/tokens';
import type { Refund } from '@/types/refund';

/**
 * Mes demandes de remboursement. Liste + bouton flottant pour ouvrir
 * une nouvelle demande à partir d'un paiement de l'historique.
 */
export default function RefundsScreen() {
  const router = useRouter();
  const pathname = usePathname();
  const insets = useSafeAreaInsets();
  const { isAuthenticated } = useSession();
  const refunds = useRefunds();
  const [modalOpen, setModalOpen] = useState(false);

  if (!isAuthenticated) {
    return (
      <SignInWall
        onSignIn={() => {
          rememberPendingRoute(pathname);
          router.push('/(auth)/login');
        }}
      />
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
              <ArrowLeft size={18} color="$slate700" />
            </YStack>
          </Pressable>
          <Paragraph fontSize={20} fontWeight="700" color="$slate900" flex={1}>
            Remboursements
          </Paragraph>
          <Pressable onPress={() => setModalOpen(true)} hitSlop={6}>
            <YStack width={36} height={36} borderRadius={18} backgroundColor={brand.primary} alignItems="center" justifyContent="center">
              <Plus size={18} color="white" />
            </YStack>
          </Pressable>
        </XStack>

        {refunds.isLoading ? (
          <YStack flex={1} alignItems="center" justifyContent="center">
            <ActivityIndicator />
          </YStack>
        ) : refunds.isError ? (
          <YStack padding="$5">
            <Paragraph color="$slate700">{extractApiErrorMessage(refunds.error)}</Paragraph>
          </YStack>
        ) : (
          <FlatList
            data={refunds.data ?? []}
            keyExtractor={(item) => item.id}
            contentContainerStyle={{ paddingHorizontal: 16, paddingVertical: 14, paddingBottom: insets.bottom + 24, gap: 10 }}
            onRefresh={() => refunds.refetch()}
            refreshing={refunds.isRefetching}
            ListEmptyComponent={
              <YStack padding="$6" alignItems="center" gap={6}>
                <Clock size={32} color="$slate500" />
                <Paragraph fontSize={14} fontWeight="700" color="$slate900">Aucune demande</Paragraph>
                <Paragraph fontSize={12} color="$slate500" textAlign="center">
                  Vous pouvez demander un remboursement depuis l'historique de paiements.
                </Paragraph>
              </YStack>
            }
            renderItem={({ item }) => <RefundRow refund={item} />}
          />
        )}

        <Modal visible={modalOpen} animationType="slide" presentationStyle="pageSheet" onRequestClose={() => setModalOpen(false)}>
          <RefundForm onClose={() => setModalOpen(false)} />
        </Modal>
      </YStack>
    </>
  );
}

function RefundRow({ refund }: { refund: Refund }) {
  const status = statusFor(refund.status);
  const relative = (() => {
    try { return formatDistanceToNow(new Date(refund.created_at), { addSuffix: true, locale: fr }); }
    catch { return ''; }
  })();
  return (
    <YStack padding={14} borderRadius={12} borderWidth={1} borderColor="$slate300" gap={8} backgroundColor="$background">
      <XStack alignItems="center" justifyContent="space-between" gap={8}>
        <XStack alignItems="center" gap={6}>
          {status.icon}
          <Paragraph fontSize={12} fontWeight="800" color={status.color} textTransform="uppercase">
            {status.label}
          </Paragraph>
        </XStack>
        <Paragraph fontSize={14} fontWeight="800" color="$slate900">
          {refund.amount.toLocaleString('fr-FR')} {refund.currency ?? 'XAF'}
        </Paragraph>
      </XStack>
      <Paragraph fontSize={13} color="$slate700" numberOfLines={2}>
        {refund.reason}
      </Paragraph>
      <XStack alignItems="center" justifyContent="space-between">
        <Paragraph fontSize={11} color="$slate500">Demandé {relative}</Paragraph>
        {refund.decision_note && (
          <Paragraph fontSize={11} color="$slate500" numberOfLines={1}>{refund.decision_note}</Paragraph>
        )}
      </XStack>
    </YStack>
  );
}

function RefundForm({ onClose }: { onClose: () => void }) {
  const insets = useSafeAreaInsets();
  const payments = usePayments();
  const request = useRequestRefund();
  const [selected, setSelected] = useState<string | null>(null);
  const [reason, setReason] = useState('');

  const submit = async () => {
    if (!selected) {
      Alert.alert('Paiement requis', 'Choisissez le paiement concerné.');
      return;
    }
    if (reason.trim().length < 10) {
      Alert.alert('Motif insuffisant', '10 caractères minimum.');
      return;
    }
    try {
      await request.mutateAsync({ paymentId: selected, reason: reason.trim() });
      onClose();
    } catch (err) {
      Alert.alert('Erreur', extractApiErrorMessage(err));
    }
  };

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
        <Pressable onPress={onClose} hitSlop={6}>
          <Paragraph fontSize={15} color="$slate700">Annuler</Paragraph>
        </Pressable>
        <Paragraph fontSize={16} fontWeight="700" color="$slate900" flex={1} textAlign="center">
          Nouvelle demande
        </Paragraph>
        <Pressable onPress={submit} disabled={request.isPending} hitSlop={6}>
          <Paragraph fontSize={15} fontWeight="700" color={brand.primary}>
            {request.isPending ? '…' : 'Envoyer'}
          </Paragraph>
        </Pressable>
      </XStack>
      <ScrollView
        contentContainerStyle={{ paddingHorizontal: 20, paddingTop: 16, paddingBottom: insets.bottom + 24, gap: 14 }}
        keyboardShouldPersistTaps="handled"
      >
        <Paragraph fontSize={13} color="$slate500" lineHeight={20}>
          Sélectionnez le paiement et expliquez la raison de votre demande de remboursement.
        </Paragraph>

        <Paragraph fontSize={12} fontWeight="700" color="$slate500" textTransform="uppercase">Paiement</Paragraph>
        {payments.isLoading ? (
          <ActivityIndicator />
        ) : (
          (payments.data ?? []).filter((p) => p.status === 'success').map((p) => (
            <Pressable key={p.id} onPress={() => setSelected(p.id)}>
              <YStack
                padding={12}
                borderRadius={10}
                borderWidth={1}
                borderColor={selected === p.id ? brand.primary : brand.slate300}
                backgroundColor={selected === p.id ? brand.primaryAlpha10 : '$background'}
                gap={4}
              >
                <Paragraph fontSize={14} fontWeight="700" color="$slate900" numberOfLines={1}>
                  {p.description ?? 'Paiement'}
                </Paragraph>
                <Paragraph fontSize={12} color="$slate500">
                  {p.amount.toLocaleString('fr-FR')} {p.currency} · {p.provider ?? 'KeyHome'}
                </Paragraph>
              </YStack>
            </Pressable>
          ))
        )}

        <YStack gap={6}>
          <Paragraph fontSize={12} fontWeight="700" color="$slate500" textTransform="uppercase">Motif</Paragraph>
          <TextInput
            value={reason}
            onChangeText={setReason}
            placeholder="Expliquez ce qui s'est passé (10 caractères minimum)…"
            placeholderTextColor={brand.slate500}
            multiline
            style={{
              borderWidth: 1,
              borderColor: brand.slate300,
              borderRadius: 12,
              padding: 12,
              fontSize: 14,
              color: brand.slate900,
              minHeight: 100,
              textAlignVertical: 'top',
            }}
          />
        </YStack>

        <Button
          backgroundColor="$brand"
          color="white"
          fontWeight="700"
          borderRadius={12}
          disabled={request.isPending}
          onPress={submit}
        >
          {request.isPending ? 'Envoi…' : 'Envoyer la demande'}
        </Button>
      </ScrollView>
    </YStack>
  );
}

function statusFor(s: string): { icon: React.ReactNode; label: string; color: string } {
  switch (s) {
    case 'completed': return { icon: <CheckCircle2 size={14} color={brand.success} />, label: 'Remboursé', color: brand.success };
    case 'processing': return { icon: <Clock size={14} color={brand.info} />, label: 'En cours', color: brand.info };
    case 'failed':
    case 'rejected': return { icon: <XCircle size={14} color={brand.danger} />, label: 'Refusé', color: brand.danger };
    default: return { icon: <Clock size={14} color={brand.warning} />, label: 'En attente', color: brand.warning };
  }
}

function SignInWall({ onSignIn }: { onSignIn: () => void }) {
  return (
    <YStack flex={1} alignItems="center" justifyContent="center" padding="$5" gap={10}>
      <Clock size={36} color="$slate500" />
      <Paragraph fontSize={15} fontWeight="700" color="$slate900" textAlign="center">
        Connectez-vous pour voir vos remboursements
      </Paragraph>
      <Pressable onPress={onSignIn} hitSlop={6}>
        <XStack backgroundColor={brand.primary} paddingHorizontal={18} paddingVertical={10} borderRadius={10}>
          <Paragraph color="white" fontWeight="700">Se connecter</Paragraph>
        </XStack>
      </Pressable>
    </YStack>
  );
}
