import { Redirect, useLocalSearchParams } from 'expo-router';

/**
 * Alias `/proprietaires/{id}` du web — le profil public d'un
 * propriétaire vit sous `/bailleurs/{username}` côté mobile. La page
 * web fait un fetch préalable pour récupérer le `username` à partir de
 * l'`id` ; côté mobile on accepte aussi `id` comme paramètre route et
 * le hook `useBailleur` interroge le même endpoint
 * `/users/{username}/public-profile` qui résout par id OU username.
 */
export default function ProprietaireAlias() {
  const { id } = useLocalSearchParams<{ id: string }>();
  if (!id) {
    return <Redirect href="/" />;
  }
  return <Redirect href={`/bailleurs/${id}` as never} />;
}
