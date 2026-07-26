import {
  BadgeCheck,
  CreditCard,
  Image as ImageIcon,
  QrCode,
  Save,
  Share2,
  ShieldCheck,
  Signpost,
  X,
} from '@tamagui/lucide-icons';
import { Image } from 'expo-image';
import * as ImagePicker from 'expo-image-picker';
import { useEffect, useMemo, useState } from 'react';
import { Alert, Modal, Pressable, ScrollView } from 'react-native';
import {
  Button,
  Input,
  Paragraph,
  Spinner,
  TextArea,
  XStack,
  YStack,
} from 'tamagui';

import { extractApiErrorMessage } from '@/api/client';
import { ENDPOINTS } from '@/api/endpoints';
import { useSession } from '@/auth/SessionProvider';
import { PhoneInput } from '@/components/PhoneInput';
import { PickerField } from '@/components/ads/PickerField';
import { ScreenHeader } from '@/components/ScreenHeader';
import { useCities } from '@/hooks/useReference';
import { useMe } from '@/hooks/useMe';
import { useProfileQr } from '@/hooks/useMarketing';
import { useUpdateProfile, useUploadAvatar } from '@/hooks/useProfile';
import { brand } from '@/theme/tokens';
import { downloadAuthedFile, shareLocalFile } from '@/utils/documents';
import { t } from '@/i18n';
import type { AuthUser } from '@/types/user';

type MarketingAction = 'businessCard' | 'profilePlacarde';

interface ProfileForm {
  firstname: string;
  lastname: string;
  phone_number: string;
  bio: string;
  city_id: string;
}

/**
 * Owner profile screen — editable identity fields + avatar upload, the
 * trust-score badge, and the offline marketing assets (QR code, business
 * card and profile yard-sign). All persisted through `/users/{id}`.
 */
export default function ProfileScreen() {
  const { isAuthenticated } = useSession();
  const me = useMe(isAuthenticated);
  const userId = me.data?.id;

  const updateProfile = useUpdateProfile(userId);
  const uploadAvatar = useUploadAvatar(userId);

  const cities = useCities();
  const [form, setForm] = useState<ProfileForm>({
    firstname: '',
    lastname: '',
    phone_number: '',
    bio: '',
    city_id: '',
  });
  const [qrOpen, setQrOpen] = useState(false);
  const [busy, setBusy] = useState<MarketingAction | null>(null);

  useEffect(() => {
    if (!me.data) return;
    setForm({
      firstname: me.data.firstname ?? '',
      lastname: me.data.lastname ?? '',
      phone_number: me.data.phone_number ?? '',
      bio: me.data.bio ?? '',
      city_id: me.data.city_id ?? '',
    });
  }, [me.data]);

  const set = <K extends keyof ProfileForm>(key: K, value: string) => {
    setForm((prev) => ({ ...prev, [key]: value }));
  };

  const dirtyPatch = useMemo<Partial<AuthUser>>(() => {
    if (!me.data) return {};
    const patch: Partial<AuthUser> = {};
    if (form.firstname.trim() !== (me.data.firstname ?? '')) {
      patch.firstname = form.firstname.trim();
    }
    if (form.lastname.trim() !== (me.data.lastname ?? '')) {
      patch.lastname = form.lastname.trim();
    }
    if (form.phone_number.trim() !== (me.data.phone_number ?? '')) {
      patch.phone_number = form.phone_number.trim();
    }
    if (form.bio.trim() !== (me.data.bio ?? '')) {
      patch.bio = form.bio.trim();
    }
    if (form.city_id !== (me.data.city_id ?? '')) {
      patch.city_id = form.city_id || null;
    }
    return patch;
  }, [form, me.data]);

  const hasChanges = Object.keys(dirtyPatch).length > 0;

  const handleSave = async () => {
    if (!hasChanges) return;
    try {
      await updateProfile.mutateAsync(dirtyPatch);
      Alert.alert(t('profile.title'), 'Profil mis à jour.');
    } catch (err) {
      Alert.alert(t('common.error'), extractApiErrorMessage(err));
    }
  };

  const handlePickAvatar = async () => {
    if (!userId) return;
    const permission = await ImagePicker.requestMediaLibraryPermissionsAsync();
    if (!permission.granted) {
      Alert.alert(t('common.error'), 'Accès à la galerie refusé.');
      return;
    }
    const result = await ImagePicker.launchImageLibraryAsync({
      mediaTypes: ['images'],
      allowsEditing: true,
      aspect: [1, 1],
      quality: 0.8,
    });
    const asset = result.canceled ? undefined : result.assets[0];
    if (!asset) return;
    const name = asset.fileName ?? `avatar-${Date.now()}.jpg`;
    const type = asset.mimeType ?? 'image/jpeg';
    try {
      await uploadAvatar.mutateAsync({ uri: asset.uri, name, type });
    } catch (err) {
      Alert.alert(t('common.error'), extractApiErrorMessage(err));
    }
  };

  const handleMarketing = async (
    action: MarketingAction,
    path: string,
    filename: string,
  ) => {
    setBusy(action);
    try {
      const uri = await downloadAuthedFile(path, filename);
      await shareLocalFile(uri);
    } catch (err) {
      Alert.alert(t('common.error'), extractApiErrorMessage(err));
    } finally {
      setBusy(null);
    }
  };

  const qr = useProfileQr(qrOpen);

  if (me.isLoading) {
    return (
      <YStack flex={1} backgroundColor="$background">
        <ScreenHeader title={t('profile.title')} />
        <YStack flex={1} alignItems="center" justifyContent="center">
          <Spinner color={brand.primary} size="large" />
        </YStack>
      </YStack>
    );
  }

  return (
    <YStack flex={1} backgroundColor="$background">
      <ScreenHeader title={t('profile.title')} />

      <ScrollView contentContainerStyle={{ padding: 16, paddingBottom: 40 }} showsVerticalScrollIndicator={false}>
        {/* Avatar + trust */}
        <YStack alignItems="center" gap={12} marginBottom={20}>
          <Pressable onPress={handlePickAvatar} disabled={uploadAvatar.isPending}>
            <YStack
              width={96}
              height={96}
              borderRadius={48}
              overflow="hidden"
              backgroundColor="$slate100"
              alignItems="center"
              justifyContent="center"
            >
              {uploadAvatar.isPending ? (
                <Spinner color={brand.primary} />
              ) : me.data?.avatar ? (
                <Image source={{ uri: me.data.avatar }} style={{ width: '100%', height: '100%' }} contentFit="cover" />
              ) : (
                <ImageIcon size={36} color={brand.primary} />
              )}
            </YStack>
            <YStack
              position="absolute"
              bottom={0}
              right={0}
              width={30}
              height={30}
              borderRadius={15}
              backgroundColor={brand.primary}
              alignItems="center"
              justifyContent="center"
              borderWidth={2}
              borderColor="$background"
            >
              <ImageIcon size={15} color="white" />
            </YStack>
          </Pressable>

          {me.data?.is_verified ? (
            <XStack alignItems="center" gap={6} backgroundColor={brand.primaryAlpha10} paddingHorizontal={12} paddingVertical={6} borderRadius={999}>
              <BadgeCheck size={15} color={brand.primary} />
              <Paragraph fontSize={12.5} fontWeight="800" color={brand.primary}>
                {t('profile.verified')}
              </Paragraph>
            </XStack>
          ) : null}

          {me.data?.trust_score != null ? (
            <XStack alignItems="center" gap={8} backgroundColor={brand.accentAlpha10} paddingHorizontal={14} paddingVertical={8} borderRadius={14}>
              <ShieldCheck size={18} color={brand.accentDark} />
              <YStack>
                <Paragraph fontSize={11} fontWeight="700" color={brand.accentDark}>
                  {t('profile.trustScore')}
                </Paragraph>
                <Paragraph fontSize={14} fontWeight="900" color="$slate900">
                  {me.data.trust_score}
                  {me.data.trust_tier_label ? ` · ${me.data.trust_tier_label}` : ''}
                </Paragraph>
              </YStack>
            </XStack>
          ) : null}
        </YStack>

        {/* Edit form */}
        <Paragraph fontSize={13} fontWeight="800" color="$slate700" marginBottom={12}>
          {t('profile.edit')}
        </Paragraph>
        <YStack gap={14}>
          <Field label={t('auth.firstname')}>
            <Input
              value={form.firstname}
              onChangeText={(v) => set('firstname', v)}
              placeholder={t('auth.firstname')}
              borderColor="$slate300"
            />
          </Field>
          <Field label={t('auth.lastname')}>
            <Input
              value={form.lastname}
              onChangeText={(v) => set('lastname', v)}
              placeholder={t('auth.lastname')}
              borderColor="$slate300"
            />
          </Field>
          <Field label={t('auth.phone')}>
            <PhoneInput
              value={form.phone_number}
              onChange={(v) => set('phone_number', v)}
            />
          </Field>
          <PickerField
            label={t('adForm.fields.city')}
            value={form.city_id}
            options={cities.data ?? []}
            onSelect={(o) => set('city_id', o.id)}
          />
          <Field label="Présentation">
            <TextArea
              value={form.bio}
              onChangeText={(v) => set('bio', v)}
              placeholder="Présentez-vous en quelques mots…"
              minHeight={100}
              borderColor="$slate300"
            />
          </Field>
        </YStack>

        <Button
          marginTop={18}
          size="$5"
          backgroundColor="$brand"
          color="white"
          fontWeight="800"
          borderRadius={14}
          disabled={!hasChanges || updateProfile.isPending}
          opacity={!hasChanges ? 0.5 : 1}
          icon={updateProfile.isPending ? <Spinner color="white" /> : <Save size={18} color="white" />}
          onPress={handleSave}
        >
          {t('common.save')}
        </Button>

        {/* Marketing assets */}
        <YStack marginTop={28} gap={6}>
          <Paragraph fontSize={16} fontWeight="900" color="$slate900">
            {t('profile.marketingTitle')}
          </Paragraph>
          <Paragraph fontSize={13} color="$slate500" lineHeight={19}>
            {t('profile.marketingSubtitle')}
          </Paragraph>
        </YStack>

        <YStack marginTop={14} gap={10}>
          <MarketingRow
            icon={<QrCode size={20} color={brand.primary} />}
            label={t('profile.qr')}
            onPress={() => setQrOpen(true)}
            busy={false}
          />
          <MarketingRow
            icon={<CreditCard size={20} color={brand.accent} />}
            label={t('profile.businessCard')}
            onPress={() =>
              handleMarketing('businessCard', ENDPOINTS.my.businessCard, 'carte-visite.pdf')
            }
            busy={busy === 'businessCard'}
          />
          <MarketingRow
            icon={<Signpost size={20} color={brand.secondary} />}
            label={t('profile.pancarte')}
            onPress={() =>
              handleMarketing('profilePlacarde', ENDPOINTS.my.profilePlacarde, 'pancarte-profil.pdf')
            }
            busy={busy === 'profilePlacarde'}
          />
        </YStack>
      </ScrollView>

      {/* QR modal */}
      <Modal visible={qrOpen} transparent animationType="fade" onRequestClose={() => setQrOpen(false)}>
        <Pressable style={{ flex: 1 }} onPress={() => setQrOpen(false)}>
          <YStack flex={1} alignItems="center" justifyContent="center" backgroundColor="rgba(10,10,15,0.6)" padding={24}>
            <Pressable onPress={() => undefined}>
              <YStack backgroundColor="$background" borderRadius={20} padding={20} gap={16} alignItems="center" width={300}>
                <XStack width="100%" alignItems="center" justifyContent="space-between">
                  <Paragraph fontSize={16} fontWeight="900" color="$slate900">
                    {t('profile.qr')}
                  </Paragraph>
                  <Pressable onPress={() => setQrOpen(false)} hitSlop={10}>
                    <X size={20} color={brand.slate500} />
                  </Pressable>
                </XStack>

                {qr.isLoading ? (
                  <YStack width={220} height={220} alignItems="center" justifyContent="center">
                    <Spinner color={brand.primary} size="large" />
                  </YStack>
                ) : qr.data?.qr_data_uri ? (
                  <YStack backgroundColor="white" padding={16} borderRadius={16}>
                    <Image
                      source={{ uri: qr.data.qr_data_uri }}
                      style={{ width: 220, height: 220 }}
                      contentFit="contain"
                    />
                  </YStack>
                ) : (
                  <Paragraph color="$slate500" textAlign="center" paddingVertical={40}>
                    QR code indisponible.
                  </Paragraph>
                )}

                {qr.data?.profile_url ? (
                  <Paragraph fontSize={12} color="$brand" textAlign="center" numberOfLines={1}>
                    {qr.data.profile_url}
                  </Paragraph>
                ) : null}
              </YStack>
            </Pressable>
          </YStack>
        </Pressable>
      </Modal>
    </YStack>
  );
}

function Field({ label, children }: { label: string; children: React.ReactNode }) {
  return (
    <YStack gap={6}>
      <Paragraph fontSize={13} fontWeight="600" color="$slate500">
        {label}
      </Paragraph>
      {children}
    </YStack>
  );
}

function MarketingRow({
  icon,
  label,
  onPress,
  busy,
}: {
  icon: React.ReactNode;
  label: string;
  onPress: () => void;
  busy: boolean;
}) {
  return (
    <Pressable onPress={onPress} disabled={busy}>
      <XStack
        alignItems="center"
        gap={14}
        paddingHorizontal={16}
        paddingVertical={15}
        borderRadius={14}
        borderWidth={1}
        borderColor="$slate300"
        backgroundColor="$background"
      >
        {icon}
        <Paragraph fontSize={15} fontWeight="700" color="$slate900" flex={1}>
          {label}
        </Paragraph>
        {busy ? <Spinner color={brand.primary} /> : <Share2 size={18} color={brand.slate500} />}
      </XStack>
    </Pressable>
  );
}
