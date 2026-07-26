import { X } from '@tamagui/lucide-icons';
import { useMemo, useState } from 'react';
import { ActivityIndicator, Modal, Pressable } from 'react-native';
import { WebView } from 'react-native-webview';
import { Paragraph, XStack, YStack } from 'tamagui';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

import type { TourConfig, TourScene } from '@/types/ad';

interface Props {
  visible: boolean;
  tourConfig: TourConfig;
  onClose: () => void;
}

/**
 * Viewer 360° NATIF (in-app) : une WebView plein écran qui rend la
 * photo équirectangulaire via Photo Sphere Viewer (même moteur que le
 * web). Le panorama est chargé depuis l'URL signée du backend ; on ne
 * quitte jamais l'app vers le site. Navigation entre pièces via une
 * barre de vignettes quand la visite a plusieurs scènes.
 */
export function TourViewer({ visible, tourConfig, onClose }: Props) {
  const insets = useSafeAreaInsets();
  const scenes = (tourConfig.scenes ?? []).filter((s) => Boolean(s.image_url));
  const defaultIdx = Math.max(
    0,
    scenes.findIndex((s) => s.id && s.id === tourConfig.default_scene),
  );
  const [index, setIndex] = useState(defaultIdx);
  const [loading, setLoading] = useState(true);

  const scene: TourScene | undefined = scenes[index] ?? scenes[0];

  const html = useMemo(() => buildViewerHtml(scene?.image_url ?? ''), [scene?.image_url]);

  return (
    <Modal visible={visible} animationType="fade" onRequestClose={onClose} statusBarTranslucent>
      <YStack flex={1} backgroundColor="#000000">
        {scene?.image_url ? (
          <WebView
            key={scene.image_url}
            originWhitelist={['*']}
            source={{ html }}
            style={{ flex: 1, backgroundColor: '#000000' }}
            onLoadEnd={() => setLoading(false)}
            allowsInlineMediaPlayback
            javaScriptEnabled
            domStorageEnabled
          />
        ) : (
          <YStack flex={1} alignItems="center" justifyContent="center">
            <Paragraph color="white">Visite 360° indisponible.</Paragraph>
          </YStack>
        )}

        {loading && scene?.image_url ? (
          <YStack position="absolute" top={0} left={0} right={0} bottom={0} alignItems="center" justifyContent="center" pointerEvents="none">
            <ActivityIndicator color="white" size="large" />
          </YStack>
        ) : null}

        {/* Fermer */}
        <Pressable
          onPress={onClose}
          hitSlop={10}
          accessibilityRole="button"
          accessibilityLabel="Fermer la visite 3D"
          style={{ position: 'absolute', top: insets.top + 8, right: 16 }}
        >
          <YStack width={40} height={40} borderRadius={20} backgroundColor="rgba(0,0,0,0.5)" alignItems="center" justifyContent="center">
            <X size={22} color="white" />
          </YStack>
        </Pressable>

        {/* Titre de la pièce + navigation multi-scènes */}
        {scenes.length > 0 ? (
          <YStack position="absolute" bottom={insets.bottom + 16} left={0} right={0} gap={10} paddingHorizontal={16}>
            {scene?.title ? (
              <YStack alignSelf="center" paddingHorizontal={14} paddingVertical={6} borderRadius={999} backgroundColor="rgba(0,0,0,0.55)">
                <Paragraph fontSize={13} fontWeight="700" color="white">
                  {scene.title}
                </Paragraph>
              </YStack>
            ) : null}
            {scenes.length > 1 ? (
              <XStack gap={8} justifyContent="center" flexWrap="wrap">
                {scenes.map((s, i) => (
                  <Pressable
                    key={s.id ?? String(i)}
                    onPress={() => {
                      if (i !== index) {
                        setLoading(true);
                        setIndex(i);
                      }
                    }}
                    accessibilityRole="button"
                    accessibilityLabel={`Pièce ${i + 1}`}
                  >
                    <YStack
                      paddingHorizontal={12}
                      paddingVertical={7}
                      borderRadius={999}
                      backgroundColor={i === index ? '#F6475F' : 'rgba(0,0,0,0.55)'}
                    >
                      <Paragraph fontSize={12} fontWeight="700" color="white">
                        {s.title ?? `Pièce ${i + 1}`}
                      </Paragraph>
                    </YStack>
                  </Pressable>
                ))}
              </XStack>
            ) : null}
          </YStack>
        ) : null}
      </YStack>
    </Modal>
  );
}

/**
 * HTML autonome chargeant Photo Sphere Viewer depuis le CDN jsDelivr et
 * affichant le panorama passé. Aucune donnée sensible : uniquement
 * l'URL image (déjà signée). Le drag/zoom est géré nativement par PSV.
 */
function buildViewerHtml(imageUrl: string): string {
  const safe = imageUrl.replace(/"/g, '&quot;');
  return `<!doctype html><html><head>
<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no" />
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@photo-sphere-viewer/core@5/index.min.css" />
<style>html,body,#v{margin:0;padding:0;width:100%;height:100%;background:#000;overflow:hidden}</style>
</head><body>
<div id="v"></div>
<script type="importmap">{"imports":{"three":"https://cdn.jsdelivr.net/npm/three@0.161.0/build/three.module.js","@photo-sphere-viewer/core":"https://cdn.jsdelivr.net/npm/@photo-sphere-viewer/core@5/index.module.js"}}</script>
<script type="module">
import { Viewer } from '@photo-sphere-viewer/core';
try {
  new Viewer({ container: document.getElementById('v'), panorama: "${safe}", navbar: false, defaultZoomLvl: 0 });
} catch (e) {
  document.getElementById('v').innerHTML = '<div style="color:#fff;font-family:sans-serif;text-align:center;padding-top:45vh">Visite indisponible</div>';
}
</script>
</body></html>`;
}
