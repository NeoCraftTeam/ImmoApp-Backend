import { AlertOctagon, RefreshCw } from '@tamagui/lucide-icons';
import { Component, type ErrorInfo, type ReactNode } from 'react';
import { Pressable, ScrollView } from 'react-native';
import { Paragraph, XStack, YStack } from 'tamagui';

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
 * "Reset" reflate l'UI en re-mounting le tree — utile pour les
 * scénarios où une page particulière a planté mais le reste de l'app
 * tourne (token expiré, route corrompue, network blip).
 *
 * Limitation : N'attrape PAS les exceptions dans les event handlers
 * asynchrones (use `Promise.catch`) ni dans setTimeout/setInterval.
 * Pour ça, il faut un global `ErrorUtils.setGlobalHandler` séparé
 * (configuré dans `Sentry.init({ enableNative: true })`).
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
      <YStack
        flex={1}
        backgroundColor="$background"
        padding={24}
        justifyContent="center"
      >
        <YStack alignItems="center" gap={12}>
          <YStack
            width={68}
            height={68}
            borderRadius={34}
            backgroundColor={`${brand.danger}20`}
            alignItems="center"
            justifyContent="center"
          >
            <AlertOctagon size={32} color={brand.danger} />
          </YStack>
          <Paragraph fontSize={20} fontWeight="800" color="$slate900" textAlign="center">
            Oups, quelque chose s'est mal passé
          </Paragraph>
          <Paragraph fontSize={14} color="$slate500" textAlign="center" lineHeight={20}>
            L'écran a planté de façon inattendue. Toutes vos données sont sauvegardées — touchez "Réessayer" pour reprendre.
          </Paragraph>
        </YStack>

        {__DEV__ && this.state.error && (
          <ScrollView
            style={{ maxHeight: 220, marginTop: 18 }}
            contentContainerStyle={{ padding: 12 }}
          >
            <YStack
              padding={12}
              borderRadius={10}
              backgroundColor="$slate100"
              borderWidth={1}
              borderColor={brand.danger}
              gap={6}
            >
              <Paragraph fontSize={11} fontWeight="800" color={brand.danger}>
                DEBUG (DEV seulement)
              </Paragraph>
              <Paragraph fontSize={11} color="$slate700" fontFamily="$body">
                {this.state.error.message}
              </Paragraph>
              {this.state.error.stack && (
                <Paragraph fontSize={10} color="$slate500" lineHeight={14}>
                  {this.state.error.stack.split('\n').slice(0, 8).join('\n')}
                </Paragraph>
              )}
            </YStack>
          </ScrollView>
        )}

        <YStack alignItems="center" marginTop={28}>
          <Pressable
            onPress={this.handleReset}
            accessibilityRole="button"
            accessibilityLabel="Réessayer après erreur"
            hitSlop={8}
          >
            <XStack
              alignItems="center"
              gap={8}
              paddingHorizontal={18}
              paddingVertical={12}
              borderRadius={999}
              backgroundColor="$brand"
            >
              <RefreshCw size={16} color="white" />
              <Paragraph fontSize={14} fontWeight="700" color="white">
                Réessayer
              </Paragraph>
            </XStack>
          </Pressable>
        </YStack>
      </YStack>
    );
  }
}
