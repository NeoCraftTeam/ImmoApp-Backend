import { Check, Minus, Plus, Sparkles } from '@tamagui/lucide-icons';
import { useRouter } from 'expo-router';
import { useEffect, useMemo, useRef, useState } from 'react';
import { Alert, Pressable, ScrollView, TextInput } from 'react-native';
import { Button, Input, Paragraph, Spinner, TextArea, XStack, YStack } from 'tamagui';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

import { extractApiErrorMessage } from '@/api/extract-error';
import { ImagePickerGrid } from '@/components/ads/ImagePickerGrid';
import { MapPicker } from '@/components/ads/MapPicker';
import { PickerField } from '@/components/ads/PickerField';
import {
  useAutosaveAd,
  useCreateAd,
  usePublishAd,
  useUpdateAd,
  type AdFormPayload,
  type PickedImage,
} from '@/hooks/useAdMutations';
import { useEnhanceDescription, useEnhanceTitle } from '@/hooks/useAiEnhance';
import {
  useAdTypes,
  useCities,
  useCreateCity,
  useCreateQuarter,
  usePropertyAttributes,
  useQuarters,
} from '@/hooks/useReference';
import { brand } from '@/theme/tokens';
import { t } from '@/i18n';
import type { Ad } from '@/types/ad';

interface FormState {
  title: string;
  description: string;
  transaction_type: 'location' | 'vente';
  price: string;
  price_period: 'mois' | 'jour';
  type_id: string;
  adresse: string;
  city_id: string;
  quarter_id: string;
  latitude: number | null;
  longitude: number | null;
  bedrooms: number;
  bathrooms: number;
  surface_area: string;
  has_parking: boolean;
  attributes: string[];
  deposit_amount: string;
  minimum_lease_duration: string;
  charges_forfaitaires: boolean;
  charges_montant_forfait: string;
  charges_eau: string;
  charges_electricite: string;
  charges_autres: string;
}

function initialState(ad?: Ad): FormState {
  return {
    title: ad?.title ?? '',
    description: ad?.description ?? '',
    transaction_type: ad?.transaction_type ?? 'location',
    price: ad?.price != null ? String(ad.price) : '',
    price_period: (ad?.price_period as 'mois' | 'jour') ?? 'mois',
    type_id: ad?.type?.id ?? '',
    adresse: ad?.adresse ?? '',
    city_id: ad?.quarter?.city_id ?? '',
    quarter_id: ad?.quarter?.id ?? '',
    latitude: ad?.location?.latitude ?? null,
    longitude: ad?.location?.longitude ?? null,
    bedrooms: ad?.bedrooms ?? 0,
    bathrooms: ad?.bathrooms ?? 0,
    surface_area: ad?.surface_area != null ? String(ad.surface_area) : '',
    has_parking: ad?.has_parking ?? false,
    attributes: ad?.attributes ?? [],
    deposit_amount: ad?.deposit_amount ?? '',
    minimum_lease_duration: ad?.minimum_lease_duration ?? '',
    charges_forfaitaires: ad?.charges_forfaitaires ?? false,
    charges_montant_forfait: ad?.charges_montant_forfait != null ? String(ad.charges_montant_forfait) : '',
    charges_eau: ad?.charges_eau != null ? String(ad.charges_eau) : '',
    charges_electricite: ad?.charges_electricite != null ? String(ad.charges_electricite) : '',
    charges_autres: ad?.charges_autres ?? '',
  };
}

const num = (s: string): number | null => {
  const v = parseFloat(s.replace(',', '.'));
  return Number.isFinite(v) ? v : null;
};

/**
 * Shared ad form (create + edit). A single scrollable form with sections
 * and a sticky bottom action bar (Save draft / Publish). In edit mode on
 * a draft, text changes autosave after a 1.5 s debounce.
 */
export function AdForm({ mode, ad }: { mode: 'create' | 'edit'; ad?: Ad }) {
  const router = useRouter();
  const insets = useSafeAreaInsets();

  const [form, setForm] = useState<FormState>(() => initialState(ad));
  const [picked, setPicked] = useState<PickedImage[]>([]);
  const [errors, setErrors] = useState<Partial<Record<keyof FormState, string>>>({});
  const [autosaveLabel, setAutosaveLabel] = useState('');

  const cities = useCities();
  const quarters = useQuarters(form.city_id || undefined);
  const adTypes = useAdTypes();
  const attributes = usePropertyAttributes();
  const createCity = useCreateCity();
  const createQuarter = useCreateQuarter();

  const createAd = useCreateAd();
  const updateAd = useUpdateAd(ad?.id);
  const publishAd = usePublishAd();
  const autosave = useAutosaveAd(ad?.id);
  const enhanceTitle = useEnhanceTitle();
  const enhanceDesc = useEnhanceDescription();

  const onEnhanceTitle = async () => {
    if (!form.title.trim() && !form.description.trim()) {
      Alert.alert('Information', 'Saisissez d’abord un titre ou une description.');
      return;
    }
    try {
      const improved = await enhanceTitle.mutateAsync({
        title: form.title,
        description: form.description,
      });
      if (improved && improved.trim()) {
        setForm((f) => ({ ...f, title: improved.trim() }));
      }
    } catch (err) {
      Alert.alert('Erreur IA', extractApiErrorMessage(err));
    }
  };

  const onEnhanceDescription = async () => {
    if (!form.description.trim()) {
      Alert.alert('Information', 'Saisissez d’abord quelques mots — l’IA développera.');
      return;
    }
    try {
      const improved = await enhanceDesc.mutateAsync({
        title: form.title,
        description: form.description,
        attributes: form.attributes,
      });
      if (improved && improved.trim()) {
        setForm((f) => ({ ...f, description: improved.trim() }));
      }
    } catch (err) {
      Alert.alert('Erreur IA', extractApiErrorMessage(err));
    }
  };

  const set = <K extends keyof FormState>(key: K, value: FormState[K]) => {
    setForm((f) => ({ ...f, [key]: value }));
    if (errors[key]) setErrors((e) => ({ ...e, [key]: undefined }));
  };

  /* ---- Draft autosave (edit mode, draft only) -------------------- */
  const isDraft = ad?.status === 'draft';
  const autosaveTimer = useRef<ReturnType<typeof setTimeout> | null>(null);
  const firstRender = useRef(true);

  useEffect(() => {
    if (mode !== 'edit' || !isDraft || !ad?.id) return;
    if (firstRender.current) {
      firstRender.current = false;
      return;
    }
    if (autosaveTimer.current) clearTimeout(autosaveTimer.current);
    autosaveTimer.current = setTimeout(() => {
      autosave.mutate(buildPayload(true), {
        onSuccess: () => setAutosaveLabel(t('adForm.autosaved')),
      });
    }, 1500);
    return () => {
      if (autosaveTimer.current) clearTimeout(autosaveTimer.current);
    };
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [form]);

  const buildPayload = (asDraft: boolean): AdFormPayload => ({
    title: form.title.trim() || undefined,
    description: form.description.trim() || undefined,
    transaction_type: form.transaction_type,
    price: num(form.price),
    price_period: form.transaction_type === 'location' ? form.price_period : null,
    type_id: form.type_id || undefined,
    adresse: form.adresse.trim() || undefined,
    quarter_id: form.quarter_id || undefined,
    latitude: form.latitude,
    longitude: form.longitude,
    bedrooms: form.bedrooms,
    bathrooms: form.bathrooms,
    surface_area: num(form.surface_area),
    has_parking: form.has_parking,
    attributes: form.attributes,
    deposit_amount: form.deposit_amount.trim() || undefined,
    minimum_lease_duration: form.minimum_lease_duration.trim() || undefined,
    charges_forfaitaires: form.charges_forfaitaires,
    charges_montant_forfait: form.charges_forfaitaires ? num(form.charges_montant_forfait) : null,
    charges_eau: !form.charges_forfaitaires ? num(form.charges_eau) : null,
    charges_electricite: !form.charges_forfaitaires ? num(form.charges_electricite) : null,
    charges_autres: form.charges_autres.trim() || undefined,
    is_draft: asDraft,
    images: picked.length ? picked : undefined,
  });

  const validateForPublish = (): boolean => {
    const e: Partial<Record<keyof FormState, string>> = {};
    if (!form.title.trim()) e.title = 'Titre requis';
    if (!form.description.trim()) e.description = 'Description requise';
    if (!form.adresse.trim()) e.adresse = 'Adresse requise';
    if (num(form.price) == null) e.price = 'Prix requis';
    if (!form.type_id) e.type_id = 'Type requis';
    if (!form.quarter_id) e.quarter_id = 'Quartier requis';
    if (num(form.surface_area) == null) e.surface_area = 'Surface requise';
    setErrors(e);
    return Object.keys(e).length === 0;
  };

  /* ---- Submit handlers ------------------------------------------- */
  // Helper : annule l'autosave en cours pour eviter qu'il n'ecrase
  // le payload du publish/saveDraft. Sans ce cancel, une frappe < 1.5 s
  // avant un tap "Publier" produit 2 mutations concurrentes (autosave
  // is_draft=true ET publish is_draft=false) — la derniere ecrit gagne
  // → ad peut rester en draft ou flipper bizarrement (P0 data race).
  const cancelPendingAutosave = () => {
    if (autosaveTimer.current) {
      clearTimeout(autosaveTimer.current);
      autosaveTimer.current = null;
    }
  };

  const saveDraft = async () => {
    if (busy) return;
    cancelPendingAutosave();
    try {
      if (mode === 'create') {
        const created = await createAd.mutateAsync(buildPayload(true));
        Alert.alert(t('adForm.savedDraft'));
        router.replace(`/ads/${created.id}` as never);
      } else {
        await updateAd.mutateAsync(buildPayload(true));
        Alert.alert(t('adForm.savedDraft'));
        router.back();
      }
    } catch (err) {
      Alert.alert(t('common.error'), extractApiErrorMessage(err));
    }
  };

  const publish = async () => {
    if (busy) return;
    if (!validateForPublish()) {
      Alert.alert(t('adForm.missingFields'), t('adForm.reviewHint'));
      return;
    }
    cancelPendingAutosave();
    try {
      if (mode === 'create') {
        const created = await createAd.mutateAsync(buildPayload(false));
        router.replace(`/ads/${created.id}` as never);
      } else {
        await updateAd.mutateAsync(buildPayload(false));
        if (isDraft && ad?.id) {
          await publishAd.mutateAsync(ad.id);
        }
        router.back();
      }
    } catch (err) {
      Alert.alert(t('common.error'), extractApiErrorMessage(err));
    }
  };

  // `busy` inclut autosave : tant qu'un autosave mutation est en
  // flight, on bloque les boutons. Evite : tap rapide "Publier"
  // pendant qu'un autosave roule → 2 writes en parallele sur la meme
  // ressource backend (race condition flag is_draft).
  const busy =
    createAd.isPending
    || updateAd.isPending
    || publishAd.isPending
    || autosave.isPending;

  const cityOptions = useMemo(() => cities.data ?? [], [cities.data]);
  const quarterOptions = useMemo(
    () => (quarters.data ?? []).map((q) => ({ id: q.id, name: q.name })),
    [quarters.data],
  );
  const typeOptions = useMemo(
    () => (adTypes.data ?? []).map((x) => ({ id: x.id, name: x.name })),
    [adTypes.data],
  );

  return (
    <YStack flex={1} backgroundColor="$background">
      <ScrollView
        contentContainerStyle={{ padding: 16, paddingBottom: 120, gap: 24 }}
        keyboardShouldPersistTaps="handled"
        showsVerticalScrollIndicator={false}
      >
        {/* --- Basics --- */}
        <Section title={t('adForm.steps.basics')}>
          <YStack gap={6}>
            <XStack alignItems="center" justifyContent="space-between">
              <Paragraph fontSize={13} fontWeight="600" color="$slate500">
                {t('adForm.fields.title')}
              </Paragraph>
              <AiEnhanceButton
                onPress={onEnhanceTitle}
                loading={enhanceTitle.isPending}
                label="IA titre"
              />
            </XStack>
            <Input
              value={form.title}
              onChangeText={(v) => set('title', v)}
              placeholder={t('adForm.fields.titlePlaceholder')}
              borderColor={errors.title ? '$danger' : '$slate300'}
            />
            {errors.title ? (
              <Paragraph fontSize={11.5} color="$danger">
                {errors.title}
              </Paragraph>
            ) : null}
          </YStack>
          <YStack gap={6}>
            <XStack alignItems="center" justifyContent="space-between">
              <Paragraph fontSize={13} fontWeight="600" color="$slate500">
                {t('adForm.fields.description')}
              </Paragraph>
              <AiEnhanceButton
                onPress={onEnhanceDescription}
                loading={enhanceDesc.isPending}
                label="IA description"
              />
            </XStack>
            <TextArea
              value={form.description}
              onChangeText={(v) => set('description', v)}
              placeholder={t('adForm.fields.descriptionPlaceholder')}
              minHeight={110}
              borderColor={errors.description ? '$danger' : '$slate300'}
            />
          </YStack>

          <SegmentedRow
            label={t('adForm.fields.transactionType')}
            value={form.transaction_type}
            options={[
              { value: 'location', label: t('adForm.fields.rent') },
              { value: 'vente', label: t('adForm.fields.sale') },
            ]}
            onChange={(v) => set('transaction_type', v as 'location' | 'vente')}
          />

          <XStack gap={12}>
            <YStack flex={2}>
              <LabeledInput
                label={`${t('adForm.fields.price')} (FCFA)`}
                value={form.price}
                onChange={(v) => set('price', v)}
                keyboardType="numeric"
                error={errors.price}
              />
            </YStack>
            {form.transaction_type === 'location' ? (
              <YStack flex={1}>
                <SegmentedRow
                  label={t('adForm.fields.pricePeriod')}
                  value={form.price_period}
                  options={[
                    { value: 'mois', label: 'Mois' },
                    { value: 'jour', label: 'Jour' },
                  ]}
                  onChange={(v) => set('price_period', v as 'mois' | 'jour')}
                />
              </YStack>
            ) : null}
          </XStack>

          <PickerField
            label={t('adForm.fields.type')}
            value={form.type_id}
            options={typeOptions}
            onSelect={(o) => set('type_id', o.id)}
            error={errors.type_id}
          />
        </Section>

        {/* --- Location --- */}
        <Section title={t('adForm.steps.location')}>
          <LabeledInput
            label={t('adForm.fields.address')}
            value={form.adresse}
            onChange={(v) => set('adresse', v)}
            error={errors.adresse}
          />
          <PickerField
            label={t('adForm.fields.city')}
            value={form.city_id}
            options={cityOptions}
            onSelect={(o) => {
              set('city_id', o.id);
              set('quarter_id', '');
            }}
            onCreate={(name) =>
              createCity.mutate({ name }, { onSuccess: (c) => set('city_id', c.id) })
            }
            createLabel="Créer la ville"
          />
          <PickerField
            label={t('adForm.fields.quarter')}
            value={form.quarter_id}
            options={quarterOptions}
            disabled={!form.city_id}
            onSelect={(o) => set('quarter_id', o.id)}
            onCreate={(name) =>
              form.city_id
                ? createQuarter.mutate(
                    { name, city_id: form.city_id },
                    { onSuccess: (q) => set('quarter_id', q.id) },
                  )
                : undefined
            }
            createLabel="Créer le quartier"
            error={errors.quarter_id}
          />
          <MapPicker
            latitude={form.latitude}
            longitude={form.longitude}
            onChange={(c) => {
              set('latitude', c.latitude);
              set('longitude', c.longitude);
            }}
          />
        </Section>

        {/* --- Features --- */}
        <Section title={t('adForm.steps.features')}>
          <Stepper label={t('adForm.fields.bedrooms')} value={form.bedrooms} onChange={(v) => set('bedrooms', v)} />
          <Stepper label={t('adForm.fields.bathrooms')} value={form.bathrooms} onChange={(v) => set('bathrooms', v)} />
          <LabeledInput
            label={t('adForm.fields.surface')}
            value={form.surface_area}
            onChange={(v) => set('surface_area', v)}
            keyboardType="numeric"
            error={errors.surface_area}
          />
          <ToggleRow label={t('adForm.fields.parking')} value={form.has_parking} onChange={(v) => set('has_parking', v)} />

          {attributes.data && attributes.data.length > 0 ? (
            <YStack gap={8}>
              <Paragraph fontSize={13} fontWeight="600" color="$slate500">
                {t('adForm.fields.attributes')}
              </Paragraph>
              <XStack flexWrap="wrap" gap={8}>
                {attributes.data.map((attr) => {
                  const slug = attr.slug ?? attr.key ?? '';
                  const active = form.attributes.includes(slug);
                  return (
                    <Pressable
                      key={slug}
                      onPress={() =>
                        set(
                          'attributes',
                          active
                            ? form.attributes.filter((a) => a !== slug)
                            : [...form.attributes, slug],
                        )
                      }
                    >
                      <XStack
                        paddingHorizontal={12}
                        paddingVertical={8}
                        borderRadius={999}
                        borderWidth={1}
                        borderColor={active ? brand.primary : brand.slate300}
                        backgroundColor={active ? brand.primaryAlpha10 : 'transparent'}
                        alignItems="center"
                        gap={6}
                      >
                        {active ? <Check size={13} color={brand.primary} /> : null}
                        <Paragraph fontSize={13} fontWeight="600" color={active ? brand.primary : '$slate700'}>
                          {attr.label}
                        </Paragraph>
                      </XStack>
                    </Pressable>
                  );
                })}
              </XStack>
            </YStack>
          ) : null}
        </Section>

        {/* --- Charges --- */}
        <Section title={t('adForm.steps.charges')}>
          <XStack gap={12}>
            <YStack flex={1}>
              <LabeledInput label={t('adForm.fields.deposit')} value={form.deposit_amount} onChange={(v) => set('deposit_amount', v)} />
            </YStack>
            <YStack flex={1}>
              <LabeledInput label={t('adForm.fields.minLease')} value={form.minimum_lease_duration} onChange={(v) => set('minimum_lease_duration', v)} />
            </YStack>
          </XStack>
          <ToggleRow label={t('adForm.fields.chargesFlat')} value={form.charges_forfaitaires} onChange={(v) => set('charges_forfaitaires', v)} />
          {form.charges_forfaitaires ? (
            <LabeledInput label={t('adForm.fields.chargesFlatAmount')} value={form.charges_montant_forfait} onChange={(v) => set('charges_montant_forfait', v)} keyboardType="numeric" />
          ) : (
            <XStack gap={12}>
              <YStack flex={1}>
                <LabeledInput label={t('adForm.fields.chargesWater')} value={form.charges_eau} onChange={(v) => set('charges_eau', v)} keyboardType="numeric" />
              </YStack>
              <YStack flex={1}>
                <LabeledInput label={t('adForm.fields.chargesElectricity')} value={form.charges_electricite} onChange={(v) => set('charges_electricite', v)} keyboardType="numeric" />
              </YStack>
            </XStack>
          )}
          <LabeledInput label={t('adForm.fields.chargesOther')} value={form.charges_autres} onChange={(v) => set('charges_autres', v)} />
        </Section>

        {/* --- Photos --- */}
        <Section title={t('adForm.steps.photos')}>
          <ImagePickerGrid existing={ad?.images ?? []} picked={picked} onChange={setPicked} />
        </Section>

        {autosaveLabel ? (
          <Paragraph fontSize={12} color={brand.success} textAlign="center" fontWeight="600">
            {autosaveLabel}
          </Paragraph>
        ) : null}
      </ScrollView>

      {/* Sticky action bar */}
      <XStack
        position="absolute"
        bottom={0}
        left={0}
        right={0}
        paddingHorizontal={16}
        paddingTop={12}
        paddingBottom={insets.bottom + 12}
        gap={10}
        backgroundColor="$background"
        borderTopWidth={0.5}
        borderTopColor="$slate300"
      >
        <Button
          flex={1}
          size="$5"
          chromeless
          borderWidth={1}
          borderColor="$slate300"
          borderRadius={14}
          disabled={busy}
          onPress={saveDraft}
        >
          <Paragraph fontWeight="700" color="$slate700">
            {t('adForm.saveDraft')}
          </Paragraph>
        </Button>
        <Button
          flex={1.4}
          size="$5"
          backgroundColor="$brand"
          color="white"
          fontWeight="800"
          borderRadius={14}
          disabled={busy}
          icon={busy ? <Spinner color="white" /> : undefined}
          onPress={publish}
        >
          {t('adForm.publish')}
        </Button>
      </XStack>
    </YStack>
  );
}

/* ---------------- sub-components ---------------- */
function AiEnhanceButton({
  onPress,
  loading,
  label,
}: {
  onPress: () => void;
  loading: boolean;
  label: string;
}) {
  return (
    <Pressable
      onPress={onPress}
      disabled={loading}
      hitSlop={6}
      accessibilityRole="button"
      accessibilityLabel={`Améliorer avec l'IA — ${label}`}
    >
      <XStack
        alignItems="center"
        gap={5}
        paddingHorizontal={9}
        paddingVertical={5}
        borderRadius={999}
        backgroundColor={brand.accentAlpha10}
        opacity={loading ? 0.6 : 1}
      >
        {loading ? (
          <Spinner color={brand.accentDark} size="small" />
        ) : (
          <Sparkles size={12} color={brand.accentDark} />
        )}
        <Paragraph fontSize={11} fontWeight="800" color={brand.accentDark}>
          {label}
        </Paragraph>
      </XStack>
    </Pressable>
  );
}

function Section({ title, children }: { title: string; children: React.ReactNode }) {
  return (
    <YStack gap={14}>
      <Paragraph fontSize={17} fontWeight="900" color="$slate900">
        {title}
      </Paragraph>
      {children}
    </YStack>
  );
}

function LabeledInput({
  label,
  value,
  onChange,
  placeholder,
  keyboardType,
  error,
}: {
  label: string;
  value: string;
  onChange: (v: string) => void;
  placeholder?: string;
  keyboardType?: 'default' | 'numeric';
  error?: string;
}) {
  return (
    <YStack gap={6}>
      <Paragraph fontSize={13} fontWeight="600" color="$slate500">
        {label}
      </Paragraph>
      <Input
        value={value}
        onChangeText={onChange}
        placeholder={placeholder}
        keyboardType={keyboardType ?? 'default'}
        size="$4"
        borderColor={error ? '$danger' : '$slate300'}
      />
      {error ? (
        <Paragraph fontSize={12} color="$danger">
          {error}
        </Paragraph>
      ) : null}
    </YStack>
  );
}

function SegmentedRow({
  label,
  value,
  options,
  onChange,
}: {
  label: string;
  value: string;
  options: { value: string; label: string }[];
  onChange: (v: string) => void;
}) {
  return (
    <YStack gap={6}>
      <Paragraph fontSize={13} fontWeight="600" color="$slate500">
        {label}
      </Paragraph>
      <XStack borderRadius={12} backgroundColor="$slate100" padding={3}>
        {options.map((opt) => {
          const active = value === opt.value;
          return (
            <Pressable key={opt.value} style={{ flex: 1 }} onPress={() => onChange(opt.value)}>
              <YStack
                paddingVertical={9}
                borderRadius={10}
                alignItems="center"
                backgroundColor={active ? brand.primary : 'transparent'}
              >
                <Paragraph fontSize={13.5} fontWeight="700" color={active ? 'white' : '$slate700'}>
                  {opt.label}
                </Paragraph>
              </YStack>
            </Pressable>
          );
        })}
      </XStack>
    </YStack>
  );
}

function Stepper({ label, value, onChange }: { label: string; value: number; onChange: (v: number) => void }) {
  return (
    <XStack alignItems="center" justifyContent="space-between">
      <Paragraph fontSize={14} fontWeight="600" color="$slate900">
        {label}
      </Paragraph>
      <XStack alignItems="center" gap={14}>
        <Pressable onPress={() => onChange(Math.max(0, value - 1))} hitSlop={8}>
          <YStack width={34} height={34} borderRadius={17} borderWidth={1} borderColor="$slate300" alignItems="center" justifyContent="center">
            <Minus size={16} color={brand.slate700} />
          </YStack>
        </Pressable>
        <Paragraph fontSize={16} fontWeight="800" color="$slate900" width={28} textAlign="center">
          {value}
        </Paragraph>
        <Pressable onPress={() => onChange(value + 1)} hitSlop={8}>
          <YStack width={34} height={34} borderRadius={17} backgroundColor={brand.primary} alignItems="center" justifyContent="center">
            <Plus size={16} color="white" />
          </YStack>
        </Pressable>
      </XStack>
    </XStack>
  );
}

function ToggleRow({ label, value, onChange }: { label: string; value: boolean; onChange: (v: boolean) => void }) {
  return (
    <Pressable onPress={() => onChange(!value)}>
      <XStack alignItems="center" justifyContent="space-between" paddingVertical={4}>
        <Paragraph fontSize={14} fontWeight="600" color="$slate900">
          {label}
        </Paragraph>
        <YStack
          width={48}
          height={28}
          borderRadius={14}
          backgroundColor={value ? brand.primary : brand.slate300}
          padding={3}
          justifyContent="center"
        >
          <YStack width={22} height={22} borderRadius={11} backgroundColor="white" alignSelf={value ? 'flex-end' : 'flex-start'} />
        </YStack>
      </XStack>
    </Pressable>
  );
}
