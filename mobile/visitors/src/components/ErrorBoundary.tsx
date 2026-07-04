import { Component, type ErrorInfo, type ReactNode } from 'react';
import { Appearance, Pressable, ScrollView, StyleSheet, Text, View } from 'react-native';

import { brand } from '@/theme/tokens';

interface Props {
  children: ReactNode;
  /** Optional reporter hook (Sentry, Crashlytics, etc.). */
  onError?: (error: Error, info: ErrorInfo) => void;
}

interface State {
  hasError: boolean;
  error: Error | null;
}

/**
 * Root error boundary. Catches uncaught JS exceptions in the React
 * tree (lifecycle, render) and renders a recoverable fallback instead
 * of the red-screen Metro overlay. Reports the error upstream via the
 * `onError` callback so Sentry can capture it.
 *
 * IMPORTANT : le fallback est rendu en **React Native pur** (View/Text),
 * jamais en composants Tamagui. Ce boundary est monté AU-DESSUS du
 * `TamaguiProvider` ; un fallback Tamagui planterait lui-même avec
 * « Can't find Tamagui configuration », masquant l'erreur d'origine
 * (bug observé sur l'écran messages). Un boundary racine ne doit
 * dépendre d'aucun provider applicatif.
 *
 * Limitation : N'attrape PAS les exceptions dans les event handlers
 * asynchrones (use `Promise.catch`) ni dans setTimeout/setInterval.
 */
export class ErrorBoundary extends Component<Props, State> {
  state: State = { hasError: false, error: null };

  static getDerivedStateFromError(error: Error): State {
    return { hasError: true, error };
  }

  componentDidCatch(error: Error, info: ErrorInfo): void {
    this.props.onError?.(error, info);
  }

  private handleReset = (): void => {
    this.setState({ hasError: false, error: null });
  };

  render(): ReactNode {
    if (!this.state.hasError) {
      return this.props.children;
    }
    // Ce boundary est monté au-dessus du provider de thème ; on lit donc
    // le schéma système directement via Appearance pour rester lisible
    // en dark mode (fond blanc → texte noir illisible sinon).
    const styles = getStyles(Appearance.getColorScheme() === 'dark');
    return (
      <View style={styles.container}>
        <View style={styles.center}>
          <View style={styles.iconCircle}>
            <Text style={styles.iconGlyph}>!</Text>
          </View>
          <Text style={styles.title}>Oups, quelque chose s'est mal passé</Text>
          <Text style={styles.subtitle}>
            L'écran a planté de façon inattendue. Touchez « Réessayer » pour reprendre.
          </Text>
        </View>

        {__DEV__ && this.state.error ? (
          <ScrollView style={styles.debugScroll} contentContainerStyle={styles.debugContent}>
            <View style={styles.debugBox}>
              <Text style={styles.debugLabel}>DEBUG (DEV seulement)</Text>
              <Text style={styles.debugMessage}>{this.state.error.message}</Text>
              {this.state.error.stack ? (
                <Text style={styles.debugStack}>
                  {this.state.error.stack.split('\n').slice(0, 8).join('\n')}
                </Text>
              ) : null}
            </View>
          </ScrollView>
        ) : null}

        <View style={styles.footer}>
          <Pressable
            onPress={this.handleReset}
            accessibilityRole="button"
            accessibilityLabel="Réessayer après erreur"
            hitSlop={8}
            style={styles.button}
          >
            <Text style={styles.buttonText}>Réessayer</Text>
          </Pressable>
        </View>
      </View>
    );
  }
}

function getStyles(dark: boolean) {
  const bg = dark ? '#0A0A0F' : '#FFFFFF';
  const titleColor = dark ? '#F5F5F7' : '#0A0A0F';
  const subtitleColor = dark ? '#9BA1A6' : '#5A5A5A';
  const debugBg = dark ? '#1C1C22' : '#F3F4F6';
  const debugMsgColor = dark ? '#E5E7EB' : '#1F2937';

  return StyleSheet.create({
    container: { flex: 1, backgroundColor: bg, padding: 24, justifyContent: 'center' },
    center: { alignItems: 'center', gap: 12 },
    iconCircle: {
      width: 68,
      height: 68,
      borderRadius: 34,
      backgroundColor: `${brand.danger}20`,
      alignItems: 'center',
      justifyContent: 'center',
    },
    iconGlyph: { fontSize: 34, fontWeight: '900', color: brand.danger },
    title: { fontSize: 20, fontWeight: '800', color: titleColor, textAlign: 'center' },
    subtitle: { fontSize: 14, color: subtitleColor, textAlign: 'center', lineHeight: 20 },
    debugScroll: { maxHeight: 220, marginTop: 18 },
    debugContent: { padding: 12 },
    debugBox: {
      padding: 12,
      borderRadius: 10,
      backgroundColor: debugBg,
      borderWidth: 1,
      borderColor: brand.danger,
      gap: 6,
    },
    debugLabel: { fontSize: 11, fontWeight: '800', color: brand.danger },
    debugMessage: { fontSize: 11, color: debugMsgColor },
    debugStack: { fontSize: 10, color: subtitleColor, lineHeight: 14 },
    footer: { alignItems: 'center', marginTop: 28 },
    button: {
      paddingHorizontal: 18,
      paddingVertical: 12,
      borderRadius: 999,
      backgroundColor: brand.primary,
    },
    buttonText: { fontSize: 14, fontWeight: '700', color: '#FFFFFF' },
  });
}
