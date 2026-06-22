import { ArrowLeft, CheckCircle2 } from '@tamagui/lucide-icons';
import { Stack, useLocalSearchParams, useRouter } from 'expo-router';
import { useMemo, useState } from 'react';
import { ActivityIndicator, Alert, KeyboardAvoidingView, Platform, Pressable, ScrollView, TextInput } from 'react-native';
import { Button, Paragraph, XStack, YStack } from 'tamagui';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

import { extractApiErrorMessage } from '@/api/client';
import { useSubmitSurvey, useSurvey } from '@/hooks/useSurveys';
import { brand } from '@/theme/tokens';
import type { SurveyAnswer, SurveyQuestion } from '@/types/survey';

/**
 * Sondage public — un écran qui collecte toutes les réponses d'un
 * coup. Chaque type de question rend un widget différent (text /
 * choice / rating / number). Sur succès, écran de remerciement.
 */
export default function SurveyDetail() {
  const router = useRouter();
  const insets = useSafeAreaInsets();
  const { slug } = useLocalSearchParams<{ slug: string }>();
  const { data: survey, isLoading, isError, error } = useSurvey(slug);
  const submit = useSubmitSurvey(slug);
  const [answers, setAnswers] = useState<Record<string, SurveyAnswer>>({});
  const [done, setDone] = useState(false);

  const update = (questionId: string, patch: Partial<SurveyAnswer>) => {
    setAnswers((prev) => ({
      ...prev,
      [questionId]: { question_id: questionId, ...prev[questionId], ...patch },
    }));
  };

  const handleSubmit = async () => {
    if (!survey) return;
    const required = survey.questions.filter((q) => q.is_required);
    for (const q of required) {
      const a = answers[q.id];
      if (!a || (a.value == null && (!a.option_ids || a.option_ids.length === 0))) {
        Alert.alert('Réponse requise', `Veuillez répondre à : ${q.prompt}`);
        return;
      }
    }
    try {
      await submit.mutateAsync({ answers: Object.values(answers), anonymous: true });
      setDone(true);
    } catch (err) {
      Alert.alert('Erreur', extractApiErrorMessage(err));
    }
  };

  if (isLoading) {
    return <YStack flex={1} alignItems="center" justifyContent="center"><ActivityIndicator /></YStack>;
  }
  if (isError || !survey) {
    return <YStack flex={1} alignItems="center" justifyContent="center" padding="$5"><Paragraph color="$slate700">{extractApiErrorMessage(error)}</Paragraph></YStack>;
  }

  if (done) {
    return (
      <YStack flex={1} alignItems="center" justifyContent="center" padding="$5" gap={12}>
        <CheckCircle2 size={56} color={brand.success} />
        <Paragraph fontSize={20} fontWeight="700" textAlign="center">Merci !</Paragraph>
        <Paragraph fontSize={14} color="$slate500" textAlign="center">
          Votre réponse a bien été enregistrée. Elle nous aide à améliorer KeyHome.
        </Paragraph>
        <Button backgroundColor="$brand" color="white" fontWeight="700" borderRadius={12} marginTop={6} onPress={() => router.back()}>
          Retour
        </Button>
      </YStack>
    );
  }

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
            <Paragraph fontSize={16} fontWeight="700" color="$slate900" flex={1} numberOfLines={1}>{survey.title}</Paragraph>
          </XStack>

          <ScrollView
            contentContainerStyle={{ padding: 20, paddingBottom: insets.bottom + 30, gap: 18 }}
            keyboardShouldPersistTaps="handled"
          >
            {survey.description && (
              <Paragraph fontSize={14} color="$slate500" lineHeight={20}>{survey.description}</Paragraph>
            )}
            {survey.questions.map((q, idx) => (
              <YStack key={q.id} gap={10}>
                <Paragraph fontSize={14} fontWeight="700" color="$slate900">
                  {idx + 1}. {q.prompt}
                  {q.is_required ? ' *' : ''}
                </Paragraph>
                {q.description && (
                  <Paragraph fontSize={12} color="$slate500">{q.description}</Paragraph>
                )}
                <QuestionWidget question={q} answer={answers[q.id]} onChange={(patch) => update(q.id, patch)} />
              </YStack>
            ))}
            <Button
              size="$5"
              backgroundColor="$brand"
              color="white"
              fontWeight="700"
              borderRadius={14}
              disabled={submit.isPending}
              onPress={handleSubmit}
            >
              {submit.isPending ? 'Envoi…' : 'Envoyer mes réponses'}
            </Button>
          </ScrollView>
        </YStack>
      </KeyboardAvoidingView>
    </>
  );
}

function QuestionWidget({
  question,
  answer,
  onChange,
}: {
  question: SurveyQuestion;
  answer?: SurveyAnswer;
  onChange: (patch: Partial<SurveyAnswer>) => void;
}) {
  if (question.kind === 'short_text' || question.kind === 'long_text') {
    return (
      <TextInput
        value={(answer?.value as string) ?? ''}
        onChangeText={(v) => onChange({ value: v })}
        placeholder="Votre réponse…"
        placeholderTextColor={brand.slate500}
        multiline={question.kind === 'long_text'}
        style={{
          borderWidth: 1,
          borderColor: brand.slate300,
          borderRadius: 12,
          paddingHorizontal: 14,
          paddingVertical: 10,
          fontSize: 14,
          color: brand.slate900,
          minHeight: question.kind === 'long_text' ? 90 : 44,
          textAlignVertical: question.kind === 'long_text' ? 'top' : 'center',
        }}
      />
    );
  }
  if (question.kind === 'number') {
    return (
      <TextInput
        value={answer?.value != null ? String(answer.value) : ''}
        onChangeText={(v) => onChange({ value: v === '' ? null : Number(v) })}
        keyboardType="numeric"
        style={{
          borderWidth: 1,
          borderColor: brand.slate300,
          borderRadius: 12,
          paddingHorizontal: 14,
          paddingVertical: 10,
          fontSize: 14,
          color: brand.slate900,
        }}
      />
    );
  }
  if (question.kind === 'rating') {
    const value = typeof answer?.value === 'number' ? answer.value : 0;
    return (
      <XStack gap={8}>
        {[1, 2, 3, 4, 5].map((i) => (
          <Pressable key={i} onPress={() => onChange({ value: i })}>
            <YStack
              width={42}
              height={42}
              borderRadius={21}
              backgroundColor={i <= value ? brand.primary : brand.slate100}
              alignItems="center"
              justifyContent="center"
              borderWidth={1}
              borderColor={i <= value ? brand.primary : brand.slate300}
            >
              <Paragraph fontSize={14} fontWeight="700" color={i <= value ? 'white' : brand.slate700}>{i}</Paragraph>
            </YStack>
          </Pressable>
        ))}
      </XStack>
    );
  }
  if (question.kind === 'single_choice') {
    const selected = answer?.option_ids?.[0];
    return (
      <YStack gap={6}>
        {(question.options ?? []).map((opt) => (
          <Pressable key={opt.id} onPress={() => onChange({ option_ids: [opt.id], value: null })}>
            <XStack
              padding={12}
              borderRadius={10}
              borderWidth={1}
              borderColor={selected === opt.id ? brand.primary : brand.slate300}
              backgroundColor={selected === opt.id ? brand.primaryAlpha10 : '$background'}
              alignItems="center"
              gap={8}
            >
              <YStack
                width={18}
                height={18}
                borderRadius={9}
                borderWidth={2}
                borderColor={selected === opt.id ? brand.primary : brand.slate300}
                alignItems="center"
                justifyContent="center"
              >
                {selected === opt.id && (
                  <YStack width={8} height={8} borderRadius={4} backgroundColor={brand.primary} />
                )}
              </YStack>
              <Paragraph fontSize={14} color="$slate900" flex={1}>{opt.label}</Paragraph>
            </XStack>
          </Pressable>
        ))}
      </YStack>
    );
  }
  if (question.kind === 'multi_choice') {
    const ids = new Set(answer?.option_ids ?? []);
    const toggle = (id: string) => {
      const next = new Set(ids);
      if (next.has(id)) next.delete(id); else next.add(id);
      onChange({ option_ids: Array.from(next), value: null });
    };
    return (
      <YStack gap={6}>
        {(question.options ?? []).map((opt) => {
          const on = ids.has(opt.id);
          return (
            <Pressable key={opt.id} onPress={() => toggle(opt.id)}>
              <XStack
                padding={12}
                borderRadius={10}
                borderWidth={1}
                borderColor={on ? brand.primary : brand.slate300}
                backgroundColor={on ? brand.primaryAlpha10 : '$background'}
                alignItems="center"
                gap={8}
              >
                <YStack
                  width={18}
                  height={18}
                  borderRadius={4}
                  borderWidth={2}
                  borderColor={on ? brand.primary : brand.slate300}
                  backgroundColor={on ? brand.primary : 'transparent'}
                  alignItems="center"
                  justifyContent="center"
                >
                  {on && <Paragraph fontSize={11} color="white" fontWeight="800">✓</Paragraph>}
                </YStack>
                <Paragraph fontSize={14} color="$slate900" flex={1}>{opt.label}</Paragraph>
              </XStack>
            </Pressable>
          );
        })}
      </YStack>
    );
  }
  return null;
}
