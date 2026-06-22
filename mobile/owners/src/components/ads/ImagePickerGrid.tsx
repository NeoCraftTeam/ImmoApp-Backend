import * as ImagePicker from 'expo-image-picker';
import { Image } from 'expo-image';
import { ImagePlus, X } from '@tamagui/lucide-icons';
import { Alert, Pressable } from 'react-native';
import { Paragraph, XStack, YStack } from 'tamagui';

import { brand } from '@/theme/tokens';
import type { PickedImage } from '@/hooks/useAdMutations';

const MAX_IMAGES = 10;

/**
 * Photo picker grid for the ad form. Shows already-uploaded images (read-
 * only thumbnails) plus newly-picked local images (removable), and an
 * "add" tile that opens the system gallery (multi-select).
 */
export function ImagePickerGrid({
  existing = [],
  picked,
  onChange,
}: {
  existing?: { id: number | string; url: string }[];
  picked: PickedImage[];
  onChange: (next: PickedImage[]) => void;
}) {
  const total = existing.length + picked.length;

  const pick = async () => {
    if (total >= MAX_IMAGES) {
      Alert.alert('Limite atteinte', `Maximum ${MAX_IMAGES} photos par annonce.`);
      return;
    }
    const perm = await ImagePicker.requestMediaLibraryPermissionsAsync();
    if (!perm.granted) {
      Alert.alert('Autorisation requise', 'Autorisez l’accès à vos photos pour en ajouter.');
      return;
    }
    const result = await ImagePicker.launchImageLibraryAsync({
      mediaTypes: ['images'],
      allowsMultipleSelection: true,
      selectionLimit: MAX_IMAGES - total,
      quality: 0.8,
    });
    if (result.canceled) return;
    const next: PickedImage[] = result.assets.map((a, i) => ({
      uri: a.uri,
      name: a.fileName ?? `photo-${Date.now()}-${i}.jpg`,
      type: a.mimeType ?? 'image/jpeg',
    }));
    onChange([...picked, ...next].slice(0, MAX_IMAGES - existing.length));
  };

  const removePicked = (uri: string) => {
    onChange(picked.filter((p) => p.uri !== uri));
  };

  return (
    <YStack gap={8}>
      <XStack flexWrap="wrap" gap={10}>
        {existing.map((img) => (
          <YStack key={String(img.id)} width={92} height={92} borderRadius={12} overflow="hidden" backgroundColor="$slate100">
            <Image source={{ uri: img.url }} style={{ width: '100%', height: '100%' }} contentFit="cover" />
          </YStack>
        ))}
        {picked.map((img) => (
          <YStack key={img.uri} width={92} height={92} borderRadius={12} overflow="hidden" backgroundColor="$slate100">
            <Image source={{ uri: img.uri }} style={{ width: '100%', height: '100%' }} contentFit="cover" />
            <Pressable onPress={() => removePicked(img.uri)} style={{ position: 'absolute', top: 4, right: 4 }} hitSlop={6}>
              <YStack width={22} height={22} borderRadius={11} backgroundColor="rgba(0,0,0,0.6)" alignItems="center" justifyContent="center">
                <X size={14} color="white" />
              </YStack>
            </Pressable>
          </YStack>
        ))}
        {total < MAX_IMAGES ? (
          <Pressable onPress={pick}>
            <YStack
              width={92}
              height={92}
              borderRadius={12}
              borderWidth={1.5}
              borderColor="$slate300"
              borderStyle="dashed"
              alignItems="center"
              justifyContent="center"
              gap={4}
            >
              <ImagePlus size={24} color={brand.primary} />
              <Paragraph fontSize={11} fontWeight="600" color="$slate500">
                Ajouter
              </Paragraph>
            </YStack>
          </Pressable>
        ) : null}
      </XStack>
      <Paragraph fontSize={12} color="$slate500">
        {total}/{MAX_IMAGES} photos
      </Paragraph>
    </YStack>
  );
}
