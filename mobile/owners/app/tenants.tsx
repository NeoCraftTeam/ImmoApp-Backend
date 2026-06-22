import { Briefcase, Mail, Phone, Plus, Trash2, Users, X } from '@tamagui/lucide-icons';
import { useState } from 'react';
import { Alert, FlatList, Modal, Pressable, ScrollView } from 'react-native';
import { Button, H1, Input, Paragraph, Spinner, XStack, YStack } from 'tamagui';

import { extractApiErrorMessage } from '@/api/client';
import { useSession } from '@/auth/SessionProvider';
import { EmptyState } from '@/components/EmptyState';
import { ScreenHeader } from '@/components/ScreenHeader';
import {
  useCreateTenant,
  useDeleteTenant,
  useTenants,
  type TenantInput,
} from '@/hooks/useTenants';
import { t } from '@/i18n';
import { brand } from '@/theme/tokens';
import type { Tenant } from '@/types/owner';

const EMPTY_FORM: TenantInput = {
  firstname: '',
  lastname: '',
  email: '',
  phone_number: '',
  id_number: '',
  profession: '',
};

export default function TenantsScreen() {
  const { isAuthenticated } = useSession();
  const { data: tenants, isLoading } = useTenants(isAuthenticated);
  const createTenant = useCreateTenant();
  const deleteTenant = useDeleteTenant();

  const [modalOpen, setModalOpen] = useState(false);
  const [form, setForm] = useState<TenantInput>(EMPTY_FORM);

  const setField = (key: keyof TenantInput, value: string) => {
    setForm((prev) => ({ ...prev, [key]: value }));
  };

  const handleDelete = (tenant: Tenant) => {
    const name = `${tenant.firstname} ${tenant.lastname ?? ''}`.trim();
    Alert.alert(t('tenants.title'), `${t('tenants.deleteConfirm')}\n${name}`, [
      { text: t('common.cancel'), style: 'cancel' },
      {
        text: t('common.delete'),
        style: 'destructive',
        onPress: () => {
          deleteTenant.mutate(tenant.id, {
            onError: (err) => Alert.alert(t('common.error'), extractApiErrorMessage(err)),
          });
        },
      },
    ]);
  };

  const handleCreate = async () => {
    if (!form.firstname.trim()) {
      Alert.alert(t('common.error'), 'Le prénom est obligatoire.');
      return;
    }
    try {
      const input: TenantInput = {
        firstname: form.firstname.trim(),
        lastname: form.lastname?.trim() || undefined,
        email: form.email?.trim() || undefined,
        phone_number: form.phone_number?.trim() || undefined,
        id_number: form.id_number?.trim() || undefined,
        profession: form.profession?.trim() || undefined,
      };
      await createTenant.mutateAsync(input);
      setForm(EMPTY_FORM);
      setModalOpen(false);
    } catch (err) {
      Alert.alert(t('common.error'), extractApiErrorMessage(err));
    }
  };

  const closeModal = () => {
    setForm(EMPTY_FORM);
    setModalOpen(false);
  };

  return (
    <YStack flex={1} backgroundColor="$background">
      <ScreenHeader
        title={t('tenants.title')}
        right={
          <Pressable onPress={() => setModalOpen(true)} hitSlop={10} accessibilityLabel={t('tenants.add')}>
            <YStack
              width={36}
              height={36}
              borderRadius={18}
              backgroundColor="$brand"
              alignItems="center"
              justifyContent="center"
            >
              <Plus size={18} color="white" />
            </YStack>
          </Pressable>
        }
      />

      {isLoading ? (
        <YStack flex={1} alignItems="center" justifyContent="center">
          <Spinner color={brand.primary} size="large" />
        </YStack>
      ) : (
        <FlatList
          data={tenants ?? []}
          keyExtractor={(item) => item.id}
          contentContainerStyle={{ paddingHorizontal: 16, paddingTop: 16, paddingBottom: 32, gap: 12 }}
          renderItem={({ item }) => (
            <TenantCard
              tenant={item}
              busy={deleteTenant.isPending}
              onDelete={() => handleDelete(item)}
            />
          )}
          ListEmptyComponent={
            <YStack height={420}>
              <EmptyState
                icon={<Users size={28} color={brand.primary} />}
                title={t('tenants.empty')}
                hint="Ajoutez vos locataires pour les associer à vos contrats de bail."
                ctaLabel={t('tenants.add')}
                onPressCta={() => setModalOpen(true)}
              />
            </YStack>
          }
        />
      )}

      <Modal
        visible={modalOpen}
        animationType="slide"
        transparent
        onRequestClose={closeModal}
      >
        <YStack flex={1} justifyContent="flex-end" backgroundColor="rgba(0,0,0,0.4)">
          <Pressable style={{ flex: 1 }} onPress={closeModal} />
          <YStack
            backgroundColor="$background"
            borderTopLeftRadius={24}
            borderTopRightRadius={24}
            paddingHorizontal={20}
            paddingTop={16}
            paddingBottom={28}
            gap={14}
            maxHeight="88%"
          >
            <XStack alignItems="center" justifyContent="space-between">
              <H1 fontSize={20} fontWeight="800">
                {t('tenants.add')}
              </H1>
              <Pressable onPress={closeModal} hitSlop={10} accessibilityLabel={t('common.close')}>
                <YStack width={32} height={32} borderRadius={16} backgroundColor="$slate100" alignItems="center" justifyContent="center">
                  <X size={16} color={brand.slate700} />
                </YStack>
              </Pressable>
            </XStack>

            <ScrollView showsVerticalScrollIndicator={false} keyboardShouldPersistTaps="handled">
              <YStack gap={12}>
                <FormField label={t('auth.firstname')}>
                  <Input
                    value={form.firstname}
                    onChangeText={(v) => setField('firstname', v)}
                    placeholder={t('auth.firstname')}
                    borderColor="$slate300"
                  />
                </FormField>
                <FormField label={t('auth.lastname')}>
                  <Input
                    value={form.lastname ?? ''}
                    onChangeText={(v) => setField('lastname', v)}
                    placeholder={t('auth.lastname')}
                    borderColor="$slate300"
                  />
                </FormField>
                <FormField label={t('tenants.email')}>
                  <Input
                    value={form.email ?? ''}
                    onChangeText={(v) => setField('email', v)}
                    placeholder={t('tenants.email')}
                    keyboardType="email-address"
                    autoCapitalize="none"
                    borderColor="$slate300"
                  />
                </FormField>
                <FormField label={t('tenants.phone')}>
                  <Input
                    value={form.phone_number ?? ''}
                    onChangeText={(v) => setField('phone_number', v)}
                    placeholder={t('tenants.phone')}
                    keyboardType="phone-pad"
                    borderColor="$slate300"
                  />
                </FormField>
                <FormField label={t('tenants.idNumber')}>
                  <Input
                    value={form.id_number ?? ''}
                    onChangeText={(v) => setField('id_number', v)}
                    placeholder={t('tenants.idNumber')}
                    borderColor="$slate300"
                  />
                </FormField>
                <FormField label={t('tenants.profession')}>
                  <Input
                    value={form.profession ?? ''}
                    onChangeText={(v) => setField('profession', v)}
                    placeholder={t('tenants.profession')}
                    borderColor="$slate300"
                  />
                </FormField>

                <Button
                  marginTop={6}
                  size="$4"
                  backgroundColor="$brand"
                  color="white"
                  fontWeight="800"
                  borderRadius={12}
                  disabled={createTenant.isPending}
                  icon={createTenant.isPending ? <Spinner color="white" /> : undefined}
                  onPress={handleCreate}
                >
                  {t('common.save')}
                </Button>
              </YStack>
            </ScrollView>
          </YStack>
        </YStack>
      </Modal>
    </YStack>
  );
}

function FormField({ label, children }: { label: string; children: React.ReactNode }) {
  return (
    <YStack gap={6}>
      <Paragraph fontSize={13} fontWeight="700" color="$slate700">
        {label}
      </Paragraph>
      {children}
    </YStack>
  );
}

function TenantCard({
  tenant,
  busy,
  onDelete,
}: {
  tenant: Tenant;
  busy: boolean;
  onDelete: () => void;
}) {
  const fullName = `${tenant.firstname} ${tenant.lastname ?? ''}`.trim() || 'Locataire';

  return (
    <YStack borderWidth={1} borderColor="$slate300" borderRadius={16} padding={14} gap={10} backgroundColor="$background">
      <XStack alignItems="center" gap={10}>
        <YStack width={42} height={42} borderRadius={21} backgroundColor={brand.primaryAlpha10} alignItems="center" justifyContent="center">
          <Paragraph fontSize={16} fontWeight="800" color={brand.primary}>
            {fullName.charAt(0).toUpperCase()}
          </Paragraph>
        </YStack>
        <Paragraph fontSize={15} fontWeight="800" color="$slate900" flex={1} numberOfLines={1}>
          {fullName}
        </Paragraph>
        <Pressable onPress={onDelete} disabled={busy} hitSlop={10} accessibilityLabel={t('common.delete')}>
          <YStack width={34} height={34} borderRadius={17} backgroundColor={`${brand.danger}12`} alignItems="center" justifyContent="center">
            <Trash2 size={16} color={brand.danger} />
          </YStack>
        </Pressable>
      </XStack>

      {tenant.phone_number ? (
        <XStack alignItems="center" gap={8}>
          <Phone size={14} color={brand.slate500} />
          <Paragraph fontSize={13} color="$slate700">
            {tenant.phone_number}
          </Paragraph>
        </XStack>
      ) : null}
      {tenant.email ? (
        <XStack alignItems="center" gap={8}>
          <Mail size={14} color={brand.slate500} />
          <Paragraph fontSize={13} color="$slate700" numberOfLines={1}>
            {tenant.email}
          </Paragraph>
        </XStack>
      ) : null}
      {tenant.profession ? (
        <XStack alignItems="center" gap={8}>
          <Briefcase size={14} color={brand.slate500} />
          <Paragraph fontSize={13} color="$slate700">
            {tenant.profession}
          </Paragraph>
        </XStack>
      ) : null}
    </YStack>
  );
}
