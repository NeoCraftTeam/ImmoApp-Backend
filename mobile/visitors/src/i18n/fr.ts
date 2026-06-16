/**
 * French strings — the default locale. Keep keys nested by feature so
 * adding a new screen only touches one block.
 */
const fr = {
  common: {
    back: 'Retour',
    next: 'Suivant',
    skip: 'Passer',
    cancel: 'Annuler',
    save: 'Enregistrer',
    retry: 'Réessayer',
    loading: 'Chargement…',
    error: 'Une erreur est survenue',
  },
  onboarding: {
    slides: [
      {
        title: 'Trouvez votre prochain logement',
        body: 'Des milliers d’annonces vérifiées, partout en Afrique de l’Ouest.',
      },
      {
        title: 'Voyez avant de vous déplacer',
        body: 'Photos haute qualité, visites 360°, scorecard du quartier — tout dans votre poche.',
      },
      {
        title: 'Contactez en un geste',
        body: 'Discutez directement avec le bailleur ou réservez une visite en quelques secondes.',
      },
    ],
    getStarted: 'Commencer',
    iHaveAccount: 'J’ai déjà un compte',
  },
  auth: {
    loginTitle: 'Bon retour',
    loginSubtitle: 'Connectez-vous pour retrouver vos favoris et vos messages.',
    registerTitle: 'Créer un compte',
    registerSubtitle: 'Quelques infos pour commencer.',
    email: 'Email',
    password: 'Mot de passe',
    firstname: 'Prénom',
    lastname: 'Nom',
    signIn: 'Se connecter',
    signUp: 'S’inscrire',
    forgotPassword: 'Mot de passe oublié ?',
    noAccount: 'Pas encore de compte ?',
    haveAccount: 'Déjà un compte ?',
    continueAsGuest: 'Continuer sans compte',
  },
  tabs: {
    home: 'Accueil',
    search: 'Recherche',
    favorites: 'Favoris',
    account: 'Compte',
  },
  home: {
    title: 'À la une',
    subtitle: 'Les annonces du moment près de chez vous',
    empty: 'Aucune annonce pour le moment',
    loadMore: 'Charger plus',
  },
  search: {
    title: 'Rechercher',
    placeholder: 'Quartier, ville, type de bien…',
    empty: 'Tapez une recherche pour commencer',
    noResults: 'Aucun résultat',
  },
  favorites: {
    title: 'Mes favoris',
    empty: 'Aucun favori pour le moment',
    emptyHint: 'Appuyez sur le ♡ d’une annonce pour l’ajouter ici.',
    signInPrompt: 'Connectez-vous pour voir vos favoris.',
  },
  account: {
    title: 'Mon compte',
    signedInAs: 'Connecté en tant que',
    settings: 'Paramètres',
    language: 'Langue',
    signOut: 'Se déconnecter',
    signIn: 'Se connecter',
    signUp: 'Créer un compte',
    guestHint: 'Vous parcourez en tant que visiteur. Connectez-vous pour sauvegarder vos favoris, contacter les bailleurs et plus.',
  },
  ad: {
    contactOwner: 'Contacter',
    addToFavorites: 'Ajouter aux favoris',
    share: 'Partager',
    keyscore: 'KeyScore',
    surface: 'Surface',
    bedrooms: 'Chambres',
    bathrooms: 'Salles de bain',
    parking: 'Parking',
    description: 'Description',
    location: 'Localisation',
    unlockToSeeAddress: 'Débloquez pour voir l’adresse exacte',
    perMonth: '/ mois',
    perDay: '/ jour',
  },
} as const;

export default fr;
