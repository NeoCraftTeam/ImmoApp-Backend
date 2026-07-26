import AsyncStorage from '@react-native-async-storage/async-storage';
import {
  createContext,
  useContext,
  useEffect,
  useMemo,
  useState,
  type ReactNode,
} from 'react';
import { useColorScheme } from 'react-native';

export type ThemeMode = 'system' | 'light' | 'dark';
export type ResolvedScheme = 'light' | 'dark';

const STORAGE_KEY = 'kh-theme-mode';

interface ThemeContextValue {
  /** Préférence choisie par l'utilisateur (persistée). */
  mode: ThemeMode;
  /** Schéma effectif appliqué (résout `system` via le réglage OS). */
  scheme: ResolvedScheme;
  /** Persiste et applique un nouveau mode. */
  setMode: (mode: ThemeMode) => void;
}

const ThemeContext = createContext<ThemeContextValue | null>(null);

/**
 * Fournit le thème de l'app avec surcharge manuelle persistée.
 *
 * `useColorScheme()` de React Native ne reflète QUE le réglage système et
 * n'est pas surchargeable — d'où l'écran Paramètres qui affichait « Clair
 * (système) » sans pouvoir basculer. Ce provider stocke le choix de
 * l'utilisateur (`system` / `light` / `dark`) dans AsyncStorage et résout
 * le schéma effectif, que le root layout passe à `<Theme>` de Tamagui.
 */
export function ThemeProvider({ children }: { children: ReactNode }): React.JSX.Element {
  const systemScheme = useColorScheme();
  const [mode, setModeState] = useState<ThemeMode>('system');

  useEffect(() => {
    let cancelled = false;
    AsyncStorage.getItem(STORAGE_KEY)
      .then((stored) => {
        if (!cancelled && (stored === 'light' || stored === 'dark' || stored === 'system')) {
          setModeState(stored);
        }
      })
      .catch(() => {
        /* AsyncStorage indisponible — on reste sur `system`. */
      });
    return () => {
      cancelled = true;
    };
  }, []);

  const value = useMemo<ThemeContextValue>(() => {
    const scheme: ResolvedScheme =
      mode === 'system' ? (systemScheme === 'dark' ? 'dark' : 'light') : mode;

    return {
      mode,
      scheme,
      setMode: (next: ThemeMode) => {
        setModeState(next);
        AsyncStorage.setItem(STORAGE_KEY, next).catch(() => {
          /* best-effort — la préférence reste au moins active en mémoire. */
        });
      },
    };
  }, [mode, systemScheme]);

  return <ThemeContext.Provider value={value}>{children}</ThemeContext.Provider>;
}

export function useAppTheme(): ThemeContextValue {
  const ctx = useContext(ThemeContext);
  if (!ctx) {
    throw new Error('useAppTheme must be used within <ThemeProvider>');
  }
  return ctx;
}
