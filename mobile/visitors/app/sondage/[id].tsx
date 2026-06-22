import { Redirect, useLocalSearchParams } from 'expo-router';

/**
 * Alias historique `/sondage/{id}` — l'app mobile route tous les
 * sondages publics par slug, mais l'écosystème web indexe certains
 * liens par UUID. On redirige vers `/surveys/{id}` qui sait gérer les
 * deux formats.
 */
export default function SondageAlias() {
  const { id } = useLocalSearchParams<{ id: string }>();
  if (!id) {
    return <Redirect href="/surveys" />;
  }
  return <Redirect href={`/surveys/${id}` as never} />;
}
