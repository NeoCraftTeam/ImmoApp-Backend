import { Eye, EyeOff } from '@tamagui/lucide-icons';
import { useState } from 'react';
import { Input, XStack, type InputProps } from 'tamagui';

import { brand } from '@/theme/tokens';

/**
 * Champ mot de passe avec bascule afficher/masquer. Reprend l'API d'un
 * `Input` Tamagui (toutes les props sont transmises) en imposant
 * `secureTextEntry` piloté par l'état local, et superpose un bouton œil
 * à droite. Utilisé sur login / register / reset-password.
 */
export function PasswordInput(props: InputProps): React.JSX.Element {
  const [visible, setVisible] = useState(false);

  return (
    <XStack alignItems="center">
      <Input
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
          <EyeOff size={20} color={brand.slate500} />
        ) : (
          <Eye size={20} color={brand.slate500} />
        )}
      </XStack>
    </XStack>
  );
}
