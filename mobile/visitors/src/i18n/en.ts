/**
 * English strings — partial coverage. The fallback chain returns the
 * French key when a translation is missing, so screens not in this
 * file degrade gracefully to French rather than blowing up at runtime.
 */
const en = {
  common: {
    back: 'Back',
    next: 'Next',
    skip: 'Skip',
    cancel: 'Cancel',
    save: 'Save',
    retry: 'Retry',
    loading: 'Loading…',
    error: 'Something went wrong',
  },
  onboarding: {
    slides: [
      {
        title: 'Find your next home',
        body: 'Thousands of verified listings across West Africa.',
      },
      {
        title: 'See before you visit',
        body: 'HQ photos, 360° tours, neighborhood scorecard — all in your pocket.',
      },
      {
        title: 'One-tap contact',
        body: 'Chat directly with the owner or book a viewing in seconds.',
      },
    ],
    getStarted: 'Get started',
    iHaveAccount: 'I already have an account',
  },
  auth: {
    loginTitle: 'Welcome back',
    loginSubtitle: 'Sign in to access your favorites and messages.',
    registerTitle: 'Create an account',
    registerSubtitle: 'A few details to get started.',
    email: 'Email',
    password: 'Password',
    firstname: 'First name',
    lastname: 'Last name',
    signIn: 'Sign in',
    signUp: 'Sign up',
    forgotPassword: 'Forgot password?',
    noAccount: 'No account yet?',
    haveAccount: 'Already have an account?',
    continueAsGuest: 'Continue without an account',
  },
  home: {
    title: 'Featured',
    subtitle: 'Top listings near you',
    empty: 'No listings yet',
    loadMore: 'Load more',
  },
  ad: {
    contactOwner: 'Contact',
    addToFavorites: 'Add to favorites',
    share: 'Share',
    keyscore: 'KeyScore',
    surface: 'Surface',
    bedrooms: 'Bedrooms',
    bathrooms: 'Bathrooms',
    parking: 'Parking',
    description: 'Description',
    location: 'Location',
    unlockToSeeAddress: 'Unlock to see exact address',
    perMonth: '/ month',
    perDay: '/ day',
  },
} as const;

export default en;
