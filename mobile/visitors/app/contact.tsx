import { ArrowLeft, CheckCircle2, Send } from '@tamagui/lucide-icons';
import { Stack, useRouter } from 'expo-router';
import { useEffect, useState } from 'react';
import { Alert, KeyboardAvoidingView, Linking, Platform, Pressable, ScrollView, TextInput } from 'react-native';
import { Button, H2, Paragraph, XStack, YStack } from 'tamagui';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

import { useMe } from '@/hooks/useMe';
import { useSession } from '@/auth/SessionProvider';
import { brand } from '@/theme/tokens';

const SUPPORT_EMAIL = 'support@keyhome.app';

/**
 * Contact support — formulaire 4 champs (nom, email, sujet, message).
 * Pré-rempli avec l'utilisateur connecté quand disponible. Sur succès,
 * affiche un état de confirmation avec le SLA 24 h.
 */
export default function Contact() {
  const router = useRouter();
  const insets = useSafeAreaInsets();
  const { isAuthenticated } = useSession();
  const me = useMe(isAuthenticated);

  const [name, setName] = useState('');
  const [email, setEmail] = useState('');
  const [subject, setSubject] = useState('');
  const [message, setMessage] = useState('');
  const [sent, setSent] = useState(false);
  const [sending, setSending] = useState(false);

  useEffect(() => {
    if (me.data) {
      setName(`${me.data.firstname ?? ''}${me.data.lastname ? ' ' + me.data.lastname : ''}`.trim());
      setEmail(me.data.email ?? '');
    }
  }, [me.data]);

  // L'app n'expose pas (encore) d'endpoint /support/contact côté
  // backend. On bascule sur un mailto: natif — l'utilisateur tap
  // "Envoyer" → l'app Mail s'ouvre avec sujet + corps pré-remplis.
  // C'est le pattern attendu en mobile et ça marche offline.
  const handleSubmit = async () => {
    if (!name.trim() || !email.trim() || !subject.trim() || message.trim().length < 10) {
      Alert.alert('Champs incomplets', 'Tous les champs sont requis (message ≥ 10 caractères).');
      return;
    }
    setSending(true);
    try {
      const body = `Bonjour,\n\n${message}\n\n— ${name}\n${email}`;
      const url = `mailto:${SUPPORT_EMAIL}?subject=${encodeURIComponent(subject)}&body=${encodeURIComponent(body)}`;
      const supported = await Linking.canOpenURL(url);
      if (!supported) {
        Alert.alert(
          'Application Mail indisponible',
          `Envoyez votre demande directement à ${SUPPORT_EMAIL}.`,
        );
        return;
      }
      await Linking.openURL(url);
      setSent(true);
    } catch {
      Alert.alert(
        'Erreur',
        `Impossible d'ouvrir l'app Mail. Écrivez à ${SUPPORT_EMAIL}.`,
      );
    } finally {
      setSending(false);
    }
  };

  return (
    <>
      <Stack.Screen options={{ headerShown: false }} />
      <KeyboardAvoidingView style={{ flex: 1 }} behavior={Platform.OS === 'ios' ? 'padding' : undefined}>
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
                <ArrowLeft size={18} color={brand.slate700} />
              </YStack>
            </Pressable>
            <H2 fontSize={20} fontWeight="700" color="$slate900" flex={1}>
              Nous contacter
            </H2>
          </XStack>

          <ScrollView
            contentContainerStyle={{
              paddingHorizontal: 20,
              paddingTop: 18,
              paddingBottom: insets.bottom + 24,
              gap: 14,
            }}
            keyboardShouldPersistTaps="handled"
          >
            {sent ? (
              <YStack alignItems="center" gap={12} marginTop={30}>
                <CheckCircle2 size={56} color={brand.success} />
                <H2 fontSize={20} fontWeight="700" textAlign="center">
                  Message envoyé
                </H2>
                <Paragraph fontSize={14} color="$slate500" textAlign="center">
                  Nous répondons sous 24 h ouvrées.
                </Paragraph>
                <Button
                  backgroundColor="$brand"
                  color="white"
                  fontWeight="700"
                  borderRadius={12}
                  marginTop={6}
                  onPress={() => router.back()}
                >
                  Retour
                </Button>
              </YStack>
            ) : (
              <>
                <Paragraph fontSize={13.5} color="$slate500" lineHeight={20}>
                  Une question, un signalement, un retour ? Notre équipe vous répond sous 24 h.
                </Paragraph>
                <Field label="Nom complet" value={name} onChange={setName} />
                <Field label="Email" value={email} onChange={setEmail} keyboardType="email-address" autoCapitalize="none" />
                <Field label="Sujet" value={subject} onChange={setSubject} />
                <Field label="Message" value={message} onChange={setMessage} multiline rows={5} />
                <Button
                  size="$5"
                  backgroundColor="$brand"
                  color="white"
                  fontWeight="700"
                  borderRadius={14}
                  icon={<Send size={16} color="white" />}
                  disabled={sending}
                  onPress={handleSubmit}
                >
                  {sending ? 'Envoi…' : 'Envoyer'}
                </Button>
              </>
            )}
          </ScrollView>
        </YStack>
      </KeyboardAvoidingView>
    </>
  );
}

function Field({
  label,
  value,
  onChange,
  multiline,
  rows,
  keyboardType,
  autoCapitalize,
}: {
  label: string;
  value: string;
  onChange: (v: string) => void;
  multiline?: boolean;
  rows?: number;
  keyboardType?: 'default' | 'email-address';
  autoCapitalize?: 'none' | 'sentences';
}) {
  return (
    <YStack gap={6}>
      <Paragraph fontSize={12} fontWeight="700" color="$slate500" textTransform="uppercase">
        {label}
      </Paragraph>
      <TextInput
        value={value}
        onChangeText={onChange}
        multiline={multiline}
        numberOfLines={rows}
        keyboardType={keyboardType ?? 'default'}
        autoCapitalize={autoCapitalize ?? 'sentences'}
        autoCorrect={false}
        placeholderTextColor={brand.slate500}
        style={{
          borderWidth: 1,
          borderColor: brand.slate300,
          borderRadius: 12,
          paddingHorizontal: 14,
          paddingVertical: 12,
          fontSize: 15,
          color: brand.slate900,
          minHeight: multiline ? 100 : undefined,
          textAlignVertical: multiline ? 'top' : 'center',
        }}
      />
    </YStack>
  );
}
