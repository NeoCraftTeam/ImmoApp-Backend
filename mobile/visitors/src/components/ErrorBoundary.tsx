import { Component, type ErrorInfo, type ReactNode } from 'react';
import { Pressable, ScrollView, StyleSheet, Text, View } from 'react-native';

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

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: '#FFFFFF', padding: 24, justifyContent: 'center' },
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
  title: { fontSize: 20, fontWeight: '800', color: '#0A0A0F', textAlign: 'center' },
  subtitle: { fontSize: 14, color: '#5A5A5A', textAlign: 'center', lineHeight: 20 },
  debugScroll: { maxHeight: 220, marginTop: 18 },
  debugContent: { padding: 12 },
  debugBox: {
    padding: 12,
    borderRadius: 10,
    backgroundColor: '#F3F4F6',
    borderWidth: 1,
    borderColor: brand.danger,
    gap: 6,
  },
  debugLabel: { fontSize: 11, fontWeight: '800', color: brand.danger },
  debugMessage: { fontSize: 11, color: '#1F2937' },
  debugStack: { fontSize: 10, color: '#5A5A5A', lineHeight: 14 },
  footer: { alignItems: 'center', marginTop: 28 },
  button: {
    paddingHorizontal: 18,
    paddingVertical: 12,
    borderRadius: 999,
    backgroundColor: brand.primary,
  },
  buttonText: { fontSize: 14, fontWeight: '700', color: '#FFFFFF' },
});
