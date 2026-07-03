import { ArrowLeft, Check, ChevronLeft, ChevronRight, Star } from '@tamagui/lucide-icons';
import { Stack, useLocalSearchParams, useRouter } from 'expo-router';
import { useMemo, useRef, useState } from 'react';
import { ActivityIndicator, Alert, Pressable, ScrollView, TextInput } from 'react-native';
import { Button, H2, Paragraph, Spinner, XStack, YStack } from 'tamagui';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

import { extractApiErrorMessage } from '@/api/client';
import { useSubmitSurvey, useSurvey } from '@/hooks/useSurveys';
import { brand } from '@/theme/tokens';
import type { SurveyQuestion } from '@/types/survey';

/** UUID v4 minimal (jeton de déduplication client — pas de sécurité requise). */
function uuidv4(): string {
  return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, (c) => {
    const r = (Math.random() * 16) | 0;
    const v = c === 'x' ? r : (r & 0x3) | 0x8;
    return v.toString(16);
  });
}

function optionLabel(opt: string | { label?: string; value?: string }): string {
  return typeof opt === 'string' ? opt : (opt.label ?? opt.value ?? '');
}

/**
 * Sondage en STEPPER — une question par étape, barre de progression,
 * Précédent/Suivant, soumission à la dernière. Gère texte libre, choix
 * unique (multiple_choice), choix multiple (checkbox) et note (rating).
 */
export default function SurveyScreen() {
  const router = useRouter();
  const insets = useSafeAreaInsets();
  const { slug } = useLocalSearchParams<{ slug: string }>();
  const { data: survey, isLoading, isError, error } = useSurvey(slug);
  const submit = useSubmitSurvey(slug);
  const clientToken = useRef(uuidv4()).current;

  const [step, setStep] = useState(0);
  // Réponses : question_id → string (texte / option / note) ou string[] (checkbox).
  const [answers, setAnswers] = useState<Record<string, string | string[]>>({});

  const questions = useMemo(
    () => (survey?.questions ?? []).slice().sort((a, b) => (a.order ?? 0) - (b.order ?? 0)),
    [survey],
  );
  const total = questions.length;
  const current = questions[step];

  const setAnswer = (qid: string, value: string | string[]) => {
    setAnswers((prev) => ({ ...prev, [qid]: value }));
  };

  const isAnswered = (q: SurveyQuestion | undefined): boolean => {
    if (!q) return false;
    const a = answers[q.id];
    if (Array.isArray(a)) return a.length > 0;
    return typeof a === 'string' && a.trim() !== '';
  };

  const handleSubmit = () => {
    const payload = Object.entries(answers)
      .filter(([, v]) => (Array.isArray(v) ? v.length > 0 : String(v).trim() !== ''))
      .map(([question_id, v]) => ({ question_id, answer: v }));
    if (payload.length === 0) return;
    submit.mutate(
      { client_token: clientToken, answers: payload },
      {
        onSuccess: () => {
          Alert.alert('Merci !', 'Vos réponses ont bien été envoyées.', [
            {
              text: 'OK',
              onPress: () => (router.canGoBack() ? router.back() : router.replace('/(tabs)/account')),
            },
          ]);
        },
        onError: (err) => Alert.alert('Erreur', extractApiErrorMessage(err)),
      },
    );
  };

  if (isLoading) {
    return (
      <YStack flex={1} backgroundColor="$background" alignItems="center" justifyContent="center">
        <ActivityIndicator />
      </YStack>
    );
  }

  if (isError || !survey || total === 0) {
    return (
      <YStack flex={1} backgroundColor="$background" alignItems="center" justifyContent="center" padding="$5" gap={12}>
        <Paragraph fontSize={14} color="$slate700" textAlign="center">
          {isError ? extractApiErrorMessage(error) : 'Ce sondage ne contient aucune question.'}
        </Paragraph>
        <Button backgroundColor="$brand" color="$brandText" onPress={() => router.back()}>
          Retour
        </Button>
      </YStack>
    );
  }

  if (survey.already_submitted) {
    return (
      <YStack flex={1} backgroundColor="$background" alignItems="center" justifyContent="center" padding="$5" gap={12}>
        <YStack width={64} height={64} borderRadius={32} backgroundColor={`${brand.success}20`} alignItems="center" justifyContent="center">
          <Check size={30} color={brand.success} />
        </YStack>
        <Paragraph fontSize={16} fontWeight="800" color="$slate900" textAlign="center">
          Merci, vous avez déjà répondu à ce sondage.
        </Paragraph>
        <Button backgroundColor="$brand" color="$brandText" onPress={() => router.back()}>
          Retour
        </Button>
      </YStack>
    );
  }

  const isLast = step === total - 1;
  const progress = ((step + 1) / total) * 100;

  return (
    <>
      <Stack.Screen options={{ headerShown: false }} />
      <YStack flex={1} backgroundColor="$background">
        <XStack paddingTop={insets.top + 8} paddingHorizontal={14} paddingBottom={10} alignItems="center" gap={10}>
          <Pressable onPress={() => router.back()} hitSlop={8} accessibilityRole="button" accessibilityLabel="Fermer">
            <YStack width={36} height={36} borderRadius={18} backgroundColor="$slate100" alignItems="center" justifyContent="center">
              <ArrowLeft size={18} color="$slate700" />
            </YStack>
          </Pressable>
          <H2 fontSize={17} fontWeight="700" color="$slate900" flex={1} numberOfLines={1}>
            {survey.title}
          </H2>
        </XStack>

        <YStack paddingHorizontal={16} gap={6} marginBottom={8}>
          <Paragraph fontSize={12} color="$slate500" fontWeight="600">
            Question {step + 1} / {total}
          </Paragraph>
          <YStack height={6} borderRadius={3} backgroundColor="$slate100" overflow="hidden">
            <YStack height="100%" width={`${progress}%`} backgroundColor="$brand" />
          </YStack>
        </YStack>

        <ScrollView contentContainerStyle={{ padding: 16, gap: 16, flexGrow: 1 }} keyboardShouldPersistTaps="handled">
          {current ? (
            <YStack gap={16}>
              <Paragraph fontSize={19} fontWeight="800" color="$slate900" lineHeight={26}>
                {current.text}
              </Paragraph>
              <QuestionWidget question={current} value={answers[current.id]} onChange={(v) => setAnswer(current.id, v)} />
            </YStack>
          ) : null}
        </ScrollView>

        <XStack padding={16} paddingBottom={insets.bottom + 16} gap={10} borderTopWidth={1} borderTopColor="$borderColor">
          {step > 0 ? (
            <Button
              flex={1}
              size="$4"
              chromeless
              borderWidth={1}
              borderColor="$borderColor"
              borderRadius={12}
              color="$slate900"
              fontWeight="700"
              icon={<ChevronLeft size={16} color="$slate700" />}
              onPress={() => setStep((s) => Math.max(0, s - 1))}
            >
              Précédent
            </Button>
          ) : null}
          {isLast ? (
            <Button
              flex={2}
              size="$4"
              backgroundColor="$brand"
              color="$brandText"
              fontWeight="800"
              borderRadius={12}
              disabled={submit.isPending || !isAnswered(current)}
              icon={submit.isPending ? <Spinner color="white" /> : undefined}
              onPress={handleSubmit}
            >
              Envoyer mes réponses
            </Button>
          ) : (
            <Button
              flex={2}
              size="$4"
              backgroundColor="$brand"
              color="$brandText"
              fontWeight="800"
              borderRadius={12}
              disabled={!isAnswered(current)}
              iconAfter={<ChevronRight size={16} color="white" />}
              onPress={() => setStep((s) => Math.min(total - 1, s + 1))}
            >
              Suivant
            </Button>
          )}
        </XStack>
      </YStack>
    </>
  );
}

function QuestionWidget({
  question,
  value,
  onChange,
}: {
  question: SurveyQuestion;
  value: string | string[] | undefined;
  onChange: (v: string | string[]) => void;
}) {
  const options = (question.options ?? []).map(optionLabel).filter((o) => o !== '');

  if (question.type === 'text') {
    return (
      <TextInput
        value={typeof value === 'string' ? value : ''}
        onChangeText={onChange}
        placeholder="Votre réponse…"
        placeholderTextColor={brand.slate500}
        multiline
        maxLength={1000}
        style={{
          minHeight: 100,
          borderWidth: 1,
          borderColor: brand.slate300,
          borderRadius: 12,
          padding: 14,
          fontSize: 15,
          color: brand.slate900,
          textAlignVertical: 'top',
        }}
        accessibilityLabel={question.text}
      />
    );
  }

  if (question.type === 'rating') {
    const rating = Number(value) || 0;
    return (
      <XStack gap={8}>
        {[1, 2, 3, 4, 5].map((n) => (
          <Pressable key={n} onPress={() => onChange(String(n))} hitSlop={6} accessibilityRole="button" accessibilityLabel={`Note ${n}`}>
            <Star size={38} color={brand.warning} fill={n <= rating ? brand.warning : 'transparent'} />
          </Pressable>
        ))}
      </XStack>
    );
  }

  const multi = question.type === 'checkbox';
  const selected: string[] = multi
    ? Array.isArray(value)
      ? value
      : []
    : typeof value === 'string' && value !== ''
      ? [value]
      : [];

  const toggle = (opt: string) => {
    if (multi) {
      onChange(selected.includes(opt) ? selected.filter((o) => o !== opt) : [...selected, opt]);
    } else {
      onChange(opt);
    }
  };

  return (
    <YStack gap={10}>
      {options.map((opt) => {
        const isSel = selected.includes(opt);
        return (
          <Pressable key={opt} onPress={() => toggle(opt)} accessibilityRole="button" accessibilityState={{ selected: isSel }}>
            <XStack
              alignItems="center"
              gap={12}
              padding={14}
              borderRadius={12}
              borderWidth={1.5}
              borderColor={isSel ? '$brand' : '$borderColor'}
              backgroundColor={isSel ? '$brandAlpha10' : '$background'}
            >
              <YStack
                width={22}
                height={22}
                borderRadius={multi ? 6 : 11}
                borderWidth={2}
                borderColor={isSel ? '$brand' : '$slate300'}
                backgroundColor={isSel ? '$brand' : 'transparent'}
                alignItems="center"
                justifyContent="center"
              >
                {isSel ? <Check size={13} color="white" /> : null}
              </YStack>
              <Paragraph flex={1} fontSize={15} color="$slate900">
                {opt}
              </Paragraph>
            </XStack>
          </Pressable>
        );
      })}
    </YStack>
  );
}
