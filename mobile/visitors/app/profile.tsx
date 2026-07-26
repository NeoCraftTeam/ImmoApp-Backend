import { ArrowLeft, Camera, CheckCircle2 } from '@tamagui/lucide-icons';
import { Image } from 'expo-image';
import * as ImagePicker from 'expo-image-picker';
import { Stack, useRouter } from 'expo-router';
import { useEffect, useState } from 'react';
import {
  ActivityIndicator,
  Alert,
  Pressable,
  ScrollView,
  TextInput,
} from 'react-native';
import { Button, H2, Paragraph, XStack, YStack } from 'tamagui';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

import { extractApiErrorMessage } from '@/api/client';
import { CityAutocomplete } from '@/components/CityAutocomplete';
import { PhoneInput } from '@/components/PhoneInput';
import { useMe } from '@/hooks/useMe';
import { useUpdateProfile, useUploadAvatar } from '@/hooks/useUpdateProfile';
import { useSession } from '@/auth/SessionProvider';
import { resolveMediaUrl } from '@/lib/media-url';
import { brand } from '@/theme/tokens';

/**
 * Profile editor — firstname / lastname / phone / city. Loads the
 * authenticated user via `useMe`, edits a local draft, and PUTs it
 * via `useUpdateProfile` when the user taps "Enregistrer".
 *
 * Avatar upload is intentionally out of scope for v0.5 — the web
 * version uses `react-easy-crop` which doesn't map cleanly to RN.
 */
export default function ProfileEdit() {
  const router = useRouter();
  const insets = useSafeAreaInsets();
  const { isAuthenticated } = useSession();
  const me = useMe();
  const update = useUpdateProfile(me.data?.id);
  const uploadAvatar = useUploadAvatar(me.data?.id);

  const [firstname, setFirstname] = useState('');
  const [lastname, setLastname] = useState('');
  const [phone, setPhone] = useState('');
  const [cityId, setCityId] = useState<string | null>(null);
  const [cityName, setCityName] = useState('');

  useEffect(() => {
    if (me.data) {
      setFirstname(me.data.firstname ?? '');
      setLastname(me.data.lastname ?? '');
      setPhone(me.data.phone_number ?? '');
      setCityId(me.data.city_id ?? null);
      setCityName(me.data.city_name ?? '');
    }
  }, [me.data]);

  if (!isAuthenticated) {
    return (
      <YStack flex={1} alignItems="center" justifyContent="center" padding="$5">
        <Paragraph color="$slate700">Connectez-vous pour accéder à votre profil.</Paragraph>
        <Button marginTop={12} backgroundColor="$brand" color="white" onPress={() => router.push('/(auth)/login')}>
          Se connecter
        </Button>
      </YStack>
    );
  }

  if (me.isError && !me.data) {
    return (
      <YStack flex={1} alignItems="center" justifyContent="center" padding="$5" gap={12}>
        <Paragraph fontSize={14} color="$slate700" textAlign="center">
          {extractApiErrorMessage(me.error)}
        </Paragraph>
        <Button backgroundColor="$brand" color="white" fontWeight="700" onPress={() => me.refetch()}>
          Réessayer
        </Button>
        <Button chromeless color="$slate500" onPress={() => router.back()}>
          Retour
        </Button>
      </YStack>
    );
  }

  if (me.isLoading || !me.data) {
    return (
      <YStack flex={1} alignItems="center" justifyContent="center">
        <ActivityIndicator />
      </YStack>
    );
  }

  const initial = firstname.charAt(0).toUpperCase() || '?';

  const handleSave = async () => {
    try {
      await update.mutateAsync({
        firstname: firstname.trim(),
        lastname: lastname.trim(),
        phone_number: phone.trim() === '' ? null : phone.trim(),
        // Le backend attend city_id (uuid), pas un texte libre — c'est
        // pourquoi la ville ne se sauvegardait pas auparavant.
        ...(cityId ? { city_id: cityId } : {}),
      });
      // Retour à l'écran précédent après succès (pas de simple alerte).
      if (router.canGoBack()) {
        router.back();
      } else {
        router.replace('/(tabs)/account');
      }
    } catch (err) {
      Alert.alert('Erreur', extractApiErrorMessage(err));
    }
  };

  const handlePickAvatar = async () => {
    try {
      const perm = await ImagePicker.requestMediaLibraryPermissionsAsync();
      if (perm.status !== 'granted') {
        Alert.alert('Permission requise', 'Autorisez l\'accès aux photos pour changer votre photo.');
        return;
      }
      const result = await ImagePicker.launchImageLibraryAsync({
        mediaTypes: ImagePicker.MediaTypeOptions.Images,
        allowsEditing: true,
        aspect: [1, 1],
        quality: 0.7,
      });
      if (result.canceled || !result.assets[0]) return;
      const asset = result.assets[0];
      const filename = asset.fileName ?? asset.uri.split('/').pop() ?? 'avatar.jpg';
      await uploadAvatar.mutateAsync({
        uri: asset.uri,
        name: filename,
        type: asset.mimeType ?? 'image/jpeg',
      });
    } catch (err) {
      Alert.alert('Erreur', extractApiErrorMessage(err));
    }
  };

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
          <H2 fontSize={20} fontWeight="700" color="$slate900" flex={1}>
            Profil
          </H2>
        </XStack>

        <ScrollView
          contentContainerStyle={{
            paddingHorizontal: 20,
            paddingTop: 20,
            paddingBottom: insets.bottom + 24,
            gap: 18,
          }}
          showsVerticalScrollIndicator={false}
        >
          <YStack alignItems="center" gap={10}>
            <Pressable onPress={handlePickAvatar} disabled={uploadAvatar.isPending}>
              <YStack
                width={104}
                height={104}
                borderRadius={52}
                backgroundColor={brand.primaryAlpha10}
                alignItems="center"
                justifyContent="center"
                overflow="hidden"
                position="relative"
              >
                {resolveMediaUrl(me.data.avatar) ? (
                  <Image source={{ uri: resolveMediaUrl(me.data.avatar)! }} style={{ width: '100%', height: '100%' }} contentFit="cover" />
                ) : (
                  <Paragraph fontSize={40} fontWeight="800" color={brand.primary}>{initial}</Paragraph>
                )}
                {uploadAvatar.isPending && (
                  <YStack position="absolute" top={0} bottom={0} left={0} right={0} backgroundColor="rgba(0,0,0,0.45)" alignItems="center" justifyContent="center">
                    <ActivityIndicator color="white" />
                  </YStack>
                )}
                <YStack
                  position="absolute"
                  bottom={0}
                  right={0}
                  width={32}
                  height={32}
                  borderRadius={16}
                  backgroundColor="$slate900"
                  alignItems="center"
                  justifyContent="center"
                >
                  <Camera size={15} color="white" />
                </YStack>
              </YStack>
            </Pressable>
            <XStack alignItems="center" gap={6}>
              <Paragraph fontSize={17} fontWeight="700" color="$slate900">
                {me.data.email}
              </Paragraph>
              {me.data.is_verified && <CheckCircle2 size={14} color={brand.success} />}
            </XStack>
          </YStack>

          <Field label="Prénom" value={firstname} onChange={setFirstname} placeholder="Prénom" />
          <Field label="Nom" value={lastname} onChange={setLastname} placeholder="Nom" />
          <YStack gap={6}>
            <Paragraph fontSize={12} fontWeight="700" color="$slate500" textTransform="uppercase">
              Téléphone
            </Paragraph>
            <PhoneInput value={phone} onChange={setPhone} />
          </YStack>
          <YStack gap={6} zIndex={20}>
            <Paragraph fontSize={12} fontWeight="700" color="$slate500" textTransform="uppercase">
              Ville
            </Paragraph>
            <CityAutocomplete
              value={cityName}
              onSelect={({ id, name }) => {
                setCityId(id);
                setCityName(name);
              }}
              onClear={() => {
                setCityId(null);
                setCityName('');
              }}
            />
          </YStack>

          <Button
            size="$5"
            backgroundColor="$brand"
            color="white"
            fontWeight="700"
            borderRadius={14}
            disabled={update.isPending}
            onPress={handleSave}
          >
            {update.isPending ? 'Enregistrement…' : 'Enregistrer'}
          </Button>

          <YStack gap={8} marginTop={6}>
            <Pressable onPress={() => router.push('/parametres')}>
              <Paragraph fontSize={13} color="$slate500" textDecorationLine="underline" textAlign="center">
                Paramètres et sécurité
              </Paragraph>
            </Pressable>
          </YStack>
        </ScrollView>
      </YStack>
    </>
  );
}

function Field({
  label,
  value,
  onChange,
  placeholder,
  keyboardType,
}: {
  label: string;
  value: string;
  onChange: (v: string) => void;
  placeholder?: string;
  keyboardType?: 'default' | 'phone-pad' | 'email-address';
}) {
  return (
    <YStack gap={6}>
      <Paragraph fontSize={12} fontWeight="700" color="$slate500" textTransform="uppercase">
        {label}
      </Paragraph>
      <TextInput
        value={value}
        onChangeText={onChange}
        placeholder={placeholder}
        placeholderTextColor={brand.slate500}
        keyboardType={keyboardType ?? 'default'}
        style={{
          borderWidth: 1,
          borderColor: brand.slate300,
          borderRadius: 12,
          paddingHorizontal: 14,
          paddingVertical: 12,
          fontSize: 15,
          color: brand.slate900,
        }}
      />
    </YStack>
  );
}
