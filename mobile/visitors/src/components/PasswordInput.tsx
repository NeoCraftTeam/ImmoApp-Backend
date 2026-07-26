import { Eye, EyeOff } from '@tamagui/lucide-icons';
import { forwardRef, useState } from 'react';
import type { TextInput } from 'react-native';
import { Input, XStack, type InputProps } from 'tamagui';

/**
 * Champ mot de passe avec bascule afficher/masquer. Reprend l'API d'un
 * `Input` Tamagui (toutes les props sont transmises) en imposant
 * `secureTextEntry` piloté par l'état local, et superpose un bouton œil
 * à droite. Le `ref` est transmis à l'`Input` interne pour permettre le
 * focus programmatique (ex. révélation progressive sur l'écran login).
 * Utilisé sur login / register / reset-password.
 */
export const PasswordInput = forwardRef<TextInput, InputProps>(
  function PasswordInput(props, ref): React.JSX.Element {
    const [visible, setVisible] = useState(false);

    return (
      <XStack alignItems="center">
        <Input
          ref={ref}
          flex={1}
          secureTextEntry={!visible}
          autoCapitalize="none"
          autoCorrect={false}
          paddingRight="$7"
          {...props}
        />
        <XStack
          position="absolute"
          right="$3"
          padding="$2"
          onPress={() => setVisible((v) => !v)}
          pressStyle={{ opacity: 0.6 }}
          accessibilityRole="button"
          accessibilityLabel={visible ? 'Masquer le mot de passe' : 'Afficher le mot de passe'}
          hitSlop={8}
        >
          {visible ? (
            <EyeOff size={20} color="$slate500" />
          ) : (
            <Eye size={20} color="$slate500" />
          )}
        </XStack>
      </XStack>
    );
  },
);
