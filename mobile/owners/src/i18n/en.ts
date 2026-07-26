/**
 * English strings. Partial — missing keys fall back to French (the
 * default locale) via `i18n.enableFallback = true`. The owner audience
 * is overwhelmingly French-speaking, so we translate the high-traffic
 * surfaces and let niche screens fall through.
 */
const en = {
  common: {
    back: 'Back',
    next: 'Next',
    skip: 'Skip',
    cancel: 'Cancel',
    save: 'Save',
    delete: 'Delete',
    edit: 'Edit',
    retry: 'Retry',
    loading: 'Loading…',
    error: 'Something went wrong',
    confirm: 'Confirm',
    close: 'Close',
    done: 'Done',
    seeAll: 'See all',
    fcfa: 'FCFA',
  },
  auth: {
    loginTitle: 'Owner area',
    loginSubtitle: 'Sign in to manage your listings and viewings.',
    registerTitle: 'Become an owner',
    registerSubtitle: 'Create your professional account in seconds.',
    email: 'Email',
    password: 'Password',
    firstname: 'First name',
    lastname: 'Last name',
    phone: 'Phone number',
    signIn: 'Sign in',
    signUp: 'Create account',
    forgotPassword: 'Forgot password?',
    noAccount: 'No account yet?',
    haveAccount: 'Already have an account?',
  },
  tabs: {
    dashboard: 'Dashboard',
    ads: 'Listings',
    viewings: 'Viewings',
    account: 'More',
  },
  dashboard: {
    title: 'Dashboard',
    greeting: 'Hello',
    newAd: 'New listing',
  },
  ads: {
    title: 'My listings',
    create: 'Create listing',
  },
  account: {
    logout: 'Sign out',
  },
} as const;

export default en;
