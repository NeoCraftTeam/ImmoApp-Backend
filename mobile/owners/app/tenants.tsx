import { FileText, Mail, Pencil, Phone, Plus, Trash2, Users, X } from '@tamagui/lucide-icons';
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
  useUpdateTenant,
  type TenantInput,
} from '@/hooks/useTenants';
import { t } from '@/i18n';
import { brand } from '@/theme/tokens';
import type { Tenant } from '@/types/owner';

const EMPTY_FORM: TenantInput = {
  name: '',
  phone: '',
  email: '',
  id_number: '',
  notes: '',
};

export default function TenantsScreen() {
  const { isAuthenticated } = useSession();
  const { data: tenants, isLoading } = useTenants(isAuthenticated);
  const createTenant = useCreateTenant();
  const updateTenant = useUpdateTenant();
  const deleteTenant = useDeleteTenant();

  const [modalOpen, setModalOpen] = useState(false);
  const [editingId, setEditingId] = useState<string | null>(null);
  const [form, setForm] = useState<TenantInput>(EMPTY_FORM);

  const setField = (key: keyof TenantInput, value: string) => {
    setForm((prev) => ({ ...prev, [key]: value }));
  };

  const openCreate = () => {
    setEditingId(null);
    setForm(EMPTY_FORM);
    setModalOpen(true);
  };

  const openEdit = (tenant: Tenant) => {
    setEditingId(tenant.id);
    setForm({
      name: tenant.name,
      phone: tenant.phone ?? '',
      email: tenant.email ?? '',
      id_number: tenant.id_number ?? '',
      notes: tenant.notes ?? '',
    });
    setModalOpen(true);
  };

  const handleDelete = (tenant: Tenant) => {
    Alert.alert(t('tenants.title'), `${t('tenants.deleteConfirm')}\n${tenant.name}`, [
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

  const handleSubmit = async () => {
    if (!form.name.trim()) {
      Alert.alert(t('common.error'), 'Le nom est obligatoire.');
      return;
    }
    try {
      const input: TenantInput = {
        name: form.name.trim(),
        phone: form.phone?.trim() || undefined,
        email: form.email?.trim() || undefined,
        id_number: form.id_number?.trim() || undefined,
        notes: form.notes?.trim() || undefined,
      };
      if (editingId) {
        await updateTenant.mutateAsync({ id: editingId, input });
      } else {
        await createTenant.mutateAsync(input);
      }
      setForm(EMPTY_FORM);
      setEditingId(null);
      setModalOpen(false);
    } catch (err) {
      Alert.alert(t('common.error'), extractApiErrorMessage(err));
    }
  };

  const closeModal = () => {
    setForm(EMPTY_FORM);
    setEditingId(null);
    setModalOpen(false);
  };

  const submitting = createTenant.isPending || updateTenant.isPending;

  return (
    <YStack flex={1} backgroundColor="$background">
      <ScreenHeader
        title={t('tenants.title')}
        right={
          <Pressable onPress={openCreate} hitSlop={10} accessibilityLabel={t('tenants.add')}>
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
              onEdit={() => openEdit(item)}
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
                onPressCta={openCreate}
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
                {editingId ? 'Modifier le locataire' : t('tenants.add')}
              </H1>
              <Pressable onPress={closeModal} hitSlop={10} accessibilityLabel={t('common.close')}>
                <YStack width={32} height={32} borderRadius={16} backgroundColor="$slate100" alignItems="center" justifyContent="center">
                  <X size={16} color={brand.slate700} />
                </YStack>
              </Pressable>
            </XStack>

            <ScrollView showsVerticalScrollIndicator={false} keyboardShouldPersistTaps="handled">
              <YStack gap={12}>
                <FormField label="Nom complet">
                  <Input
                    value={form.name}
                    onChangeText={(v) => setField('name', v)}
                    placeholder="ex. Marie Ngo Bell"
                    autoCapitalize="words"
                    borderColor="$slate300"
                  />
                </FormField>
                <FormField label={t('tenants.phone')}>
                  <Input
                    value={form.phone ?? ''}
                    onChangeText={(v) => setField('phone', v)}
                    placeholder={t('tenants.phone')}
                    keyboardType="phone-pad"
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
                <FormField label={t('tenants.idNumber')}>
                  <Input
                    value={form.id_number ?? ''}
                    onChangeText={(v) => setField('id_number', v)}
                    placeholder={t('tenants.idNumber')}
                    borderColor="$slate300"
                  />
                </FormField>
                <FormField label="Notes">
                  <Input
                    value={form.notes ?? ''}
                    onChangeText={(v) => setField('notes', v)}
                    placeholder="Notes internes (garant, situation…)"
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
                  disabled={submitting}
                  icon={submitting ? <Spinner color="white" /> : undefined}
                  onPress={handleSubmit}
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
  onEdit,
  onDelete,
}: {
  tenant: Tenant;
  busy: boolean;
  onEdit: () => void;
  onDelete: () => void;
}) {
  const fullName = tenant.name.trim() || 'Locataire';

  return (
    <YStack borderWidth={1} borderColor="$slate300" borderRadius={16} padding={14} gap={10} backgroundColor="$background">
      <XStack alignItems="center" gap={10}>
        <YStack width={42} height={42} borderRadius={21} backgroundColor={brand.primaryAlpha10} alignItems="center" justifyContent="center">
          <Paragraph fontSize={16} fontWeight="800" color={brand.primary}>
            {fullName.charAt(0).toUpperCase()}
          </Paragraph>
        </YStack>
        <YStack flex={1} gap={1}>
          <Paragraph fontSize={15} fontWeight="800" color="$slate900" numberOfLines={1}>
            {fullName}
          </Paragraph>
          {tenant.lease_contracts_count != null && tenant.lease_contracts_count > 0 ? (
            <Paragraph fontSize={11.5} color="$slate500">
              {tenant.lease_contracts_count} contrat
              {tenant.lease_contracts_count > 1 ? 's' : ''} de bail
            </Paragraph>
          ) : null}
        </YStack>
        <Pressable onPress={onEdit} hitSlop={10} accessibilityLabel="Modifier le locataire">
          <YStack width={34} height={34} borderRadius={17} backgroundColor={brand.primaryAlpha10} alignItems="center" justifyContent="center">
            <Pencil size={15} color={brand.primary} />
          </YStack>
        </Pressable>
        <Pressable onPress={onDelete} disabled={busy} hitSlop={10} accessibilityLabel={t('common.delete')}>
          <YStack width={34} height={34} borderRadius={17} backgroundColor={`${brand.danger}12`} alignItems="center" justifyContent="center">
            <Trash2 size={16} color={brand.danger} />
          </YStack>
        </Pressable>
      </XStack>

      {tenant.phone ? (
        <XStack alignItems="center" gap={8}>
          <Phone size={14} color={brand.slate500} />
          <Paragraph fontSize={13} color="$slate700">
            {tenant.phone}
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
      {tenant.notes ? (
        <XStack alignItems="flex-start" gap={8}>
          <FileText size={14} color={brand.slate500} />
          <Paragraph fontSize={13} color="$slate700" flex={1}>
            {tenant.notes}
          </Paragraph>
        </XStack>
      ) : null}
    </YStack>
  );
}
