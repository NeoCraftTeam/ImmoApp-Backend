import { CheckCircle2, Info, XCircle } from '@tamagui/lucide-icons';
import { useEffect, useRef, useState } from 'react';
import { Animated, Easing, Pressable } from 'react-native';
import { Paragraph, XStack } from 'tamagui';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

import { useReducedMotion } from '@/hooks/useReducedMotion';
import { subscribeToast, type ToastPayload } from '@/services/toast';
import { brand } from '@/theme/tokens';

/**
 * Hôte de toasts monté une seule fois à la racine. Affiche un toast à la
 * fois (le dernier gagne), animé (slide + fade), auto-dismiss, avec une
 * action optionnelle. Respecte reduced-motion.
 */
export function ToastHost() {
  const insets = useSafeAreaInsets();
  const reducedMotion = useReducedMotion();
  const [toast, setToast] = useState<ToastPayload | null>(null);
  const translateY = useRef(new Animated.Value(60)).current;
  const opacity = useRef(new Animated.Value(0)).current;
  const hideTimer = useRef<ReturnType<typeof setTimeout> | null>(null);

  useEffect(() => {
    return subscribeToast((next) => {
      if (hideTimer.current) clearTimeout(hideTimer.current);
      setToast(next);
    });
  }, []);

  useEffect(() => {
    if (!toast) return;
    if (reducedMotion) {
      translateY.setValue(0);
      opacity.setValue(1);
    } else {
      translateY.setValue(60);
      opacity.setValue(0);
      Animated.parallel([
        Animated.timing(translateY, {
          toValue: 0,
          duration: 220,
          easing: Easing.out(Easing.cubic),
          useNativeDriver: true,
        }),
        Animated.timing(opacity, { toValue: 1, duration: 180, useNativeDriver: true }),
      ]).start();
    }

    hideTimer.current = setTimeout(() => dismiss(), toast.durationMs);
    return () => {
      if (hideTimer.current) clearTimeout(hideTimer.current);
    };
  }, [toast?.id]);

  const dismiss = (): void => {
    if (reducedMotion) {
      setToast(null);
      return;
    }
    Animated.parallel([
      Animated.timing(translateY, { toValue: 60, duration: 180, useNativeDriver: true }),
      Animated.timing(opacity, { toValue: 0, duration: 160, useNativeDriver: true }),
    ]).start(({ finished }) => {
      if (finished) setToast(null);
    });
  };

  if (!toast) return null;

  const accent =
    toast.type === 'success'
      ? brand.success
      : toast.type === 'error'
        ? brand.danger
        : brand.primary;
  const Icon =
    toast.type === 'success' ? CheckCircle2 : toast.type === 'error' ? XCircle : Info;

  return (
    <Animated.View
      pointerEvents="box-none"
      style={{
        position: 'absolute',
        left: 12,
        right: 12,
        bottom: insets.bottom + 16,
        transform: [{ translateY }],
        opacity,
      }}
    >
      <XStack
        backgroundColor="$slate900"
        borderRadius={14}
        paddingVertical={12}
        paddingHorizontal={14}
        alignItems="center"
        gap={10}
        shadowColor="rgba(0,0,0,0.3)"
        shadowOffset={{ width: 0, height: 4 }}
        shadowOpacity={1}
        shadowRadius={12}
        elevation={6}
      >
        <Icon size={20} color={accent} />
        <Paragraph flex={1} fontSize={14} fontWeight="600" color="white" numberOfLines={2}>
          {toast.message}
        </Paragraph>
        {toast.actionLabel ? (
          <Pressable
            onPress={() => {
              toast.onAction?.();
              dismiss();
            }}
            hitSlop={8}
          >
            <Paragraph fontSize={14} fontWeight="800" color={accent}>
              {toast.actionLabel}
            </Paragraph>
          </Pressable>
        ) : null}
      </XStack>
    </Animated.View>
  );
}
