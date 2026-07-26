# 🧪 PROMPT TESTS COMPLETS — Application KeyHome
## Tous modules · Backend Laravel · Frontend Next.js · Sécurité globale

> **Stack :** Laravel (API) · Next.js (Frontend) · Flutter (Mobile) · Clerk (Auth front) · Sanctum (Auth API)
> **Objectif :** Couvrir 100% des flux critiques — auth, propriétés, paiements, réservations,
> messagerie, notifications, admin, géolocalisation, uploads, abonnements.

---

## 🎯 PHILOSOPHIE GLOBALE

```
Règles non négociables :
- Un test = un comportement = une assertion principale
- Zéro appel réseau réel (Http::fake Laravel / MSW Next.js / mock Clerk)
- Zéro dépendance entre tests (chaque test est autonome)
- Zéro donnée de production
- Nommage : it_should_[comportement]_when_[condition]
- Factories pour toutes les données — jamais de création manuelle en test
- Les tests de sécurité sont aussi importants que les tests fonctionnels

Seuils de couverture minimaux :
  Backend  → Lines ≥ 85%, Methods ≥ 80%
  Frontend → Lines ≥ 80%, Functions ≥ 80%, Branches ≥ 70%
  Modules critiques (auth, paiement, admin) → 100% des chemins couverts
```

---

## ⚙️ CONFIGURATION GLOBALE

### Backend — `phpunit.xml`

```xml
<php>
    <env name="APP_ENV"            value="testing"/>
    <env name="DB_CONNECTION"      value="sqlite"/>
    <env name="DB_DATABASE"        value=":memory:"/>
    <env name="QUEUE_CONNECTION"   value="sync"/>
    <env name="MAIL_MAILER"        value="array"/>
    <env name="CACHE_DRIVER"       value="array"/>
    <env name="FILESYSTEM_DISK"    value="fake"/>
    <env name="PAYMENT_GATEWAY"    value="flutterwave"/>
    <env name="FLW_WEBHOOK_SECRET" value="test_webhook_secret"/>
    <env name="CLERK_SECRET_KEY"   value="sk_test_fake_clerk_key"/>
</php>
```

### Frontend — `vitest.config.ts`

```typescript
export default defineConfig({
  plugins: [react()],
  test: {
    environment: 'jsdom',
    setupFiles:  ['./tests/setup.ts'],
    globals:     true,
    coverage: {
      provider: 'v8',
      thresholds: { lines: 80, functions: 80, branches: 70 },
    },
  },
});
```

### Frontend — `tests/setup.ts`

```typescript
import '@testing-library/jest-dom';
import { server } from './mocks/server';
// Mock Clerk globalement — NE JAMAIS appeler Clerk réel en test
vi.mock('@clerk/nextjs', () => ({
  useAuth:        () => ({ isSignedIn: true, userId: 'user_test_123', getToken: async () => 'fake_jwt' }),
  useUser:        () => ({ user: { id: 'user_test_123', fullName: 'Test User', emailAddresses: [{ emailAddress: 'test@keyhome.test' }] } }),
  currentUser:    async () => ({ id: 'user_test_123' }),
  auth:           async () => ({ userId: 'user_test_123' }),
  ClerkProvider:  ({ children }: any) => children,
  SignIn:         () => <div data-testid="clerk-signin" />,
  SignUp:         () => <div data-testid="clerk-signup" />,
  UserButton:     () => <div data-testid="clerk-userbutton" />,
}));
beforeAll(()  => server.listen({ onUnhandledRequest: 'error' }));
afterEach(()  => server.resetHandlers());
afterAll(()   => server.close());
```

---

## 🗂️ MODULE 1 — AUTHENTIFICATION

### 1.1 Backend — Synchronisation Clerk ↔ Laravel

**Fichier :** `tests/Feature/Auth/ClerkWebhookTest.php`

```php
// Tester le webhook Clerk qui synchronise les utilisateurs en base Laravel

/** @test */
public function it_should_create_user_in_database_when_clerk_fires_user_created_event(): void
// Payload : { type: 'user.created', data: { id: 'user_clerk_xxx', email_addresses: [...] } }
// Vérifier : User créé en base avec le bon clerk_id et email

/** @test */
public function it_should_update_user_when_clerk_fires_user_updated_event(): void
// Mettre à jour first_name, last_name, profile_image_url

/** @test */
public function it_should_soft_delete_user_when_clerk_fires_user_deleted_event(): void
// User doit être soft-deleted, ses données conservées

/** @test */
public function it_should_reject_clerk_webhook_when_svix_signature_is_invalid(): void
// Header svix-signature absent ou faux → 401, rien en base

/** @test */
public function it_should_reject_clerk_webhook_when_svix_timestamp_is_too_old(): void
// Timestamp > 5 minutes → 401 (replay attack protection)

/** @test */
public function it_should_be_idempotent_when_same_clerk_event_is_received_twice(): void
// Même user.created reçu 2 fois → 1 seul User en base, pas de doublon
```

**Fichier :** `tests/Feature/Auth/SanctumAuthTest.php`

```php
/** @test */
public function it_should_return_401_on_protected_routes_without_bearer_token(): void

/** @test */
public function it_should_return_401_when_bearer_token_is_expired(): void

/** @test */
public function it_should_return_401_when_bearer_token_is_malformed(): void
// Token : 'Bearer not.a.valid.jwt' → 401

/** @test */
public function it_should_return_401_when_clerk_user_no_longer_exists(): void
// Token valide mais le clerk_id n'existe plus en base → 401

/** @test */
public function it_should_correctly_resolve_authenticated_user_from_clerk_token(): void
// Vérifier que $request->user() retourne le bon User Laravel
```

### 1.2 Frontend — Flux d'authentification

**Fichier :** `tests/auth/AuthGuard.test.tsx`

```typescript
describe('Protection des routes', () => {

  it('should redirect unauthenticated user to /sign-in', async () => {
    // Mock Clerk : isSignedIn = false
    vi.mocked(useAuth).mockReturnValue({ isSignedIn: false, userId: null, getToken: async () => null });
    render(<ProtectedPage />);
    // Vérifier redirection vers /sign-in
  });

  it('should render protected content when user is authenticated', () => {
    // Mock Clerk : isSignedIn = true (défaut dans setup.ts)
    render(<ProtectedPage />);
    expect(screen.getByTestId('protected-content')).toBeInTheDocument();
  });

  it('should redirect to /dashboard after successful sign-in', async () => {
    // Simuler callback Clerk après auth
  });

  it('should clear all local state on sign-out', async () => {
    // sessionStorage, state React, etc. doivent être nettoyés
  });

  it('should show loading skeleton while Clerk is initializing', () => {
    vi.mocked(useAuth).mockReturnValue({ isLoaded: false } as any);
    render(<ProtectedPage />);
    expect(screen.getByTestId('auth-skeleton')).toBeInTheDocument();
  });
});
```

---

## 🗂️ MODULE 2 — GESTION DES PROPRIÉTÉS

### 2.1 Backend

**Fichier :** `tests/Feature/Property/PropertyCrudTest.php`

```php
/** @test */
public function it_should_return_paginated_properties_list_for_authenticated_user(): void
// GET /api/v1/properties → 200, structure { data, meta: { current_page, total } }

/** @test */
public function it_should_filter_properties_by_city(): void
// GET /api/v1/properties?city=Douala → uniquement les propriétés de Douala

/** @test */
public function it_should_filter_properties_by_price_range(): void
// GET /api/v1/properties?min_price=100000&max_price=500000

/** @test */
public function it_should_filter_properties_by_type(): void
// GET /api/v1/properties?type=apartment|villa|studio|commercial

/** @test */
public function it_should_return_property_detail_with_all_relations(): void
// GET /api/v1/properties/{id} → inclut images, owner, amenities, location

/** @test */
public function it_should_return_404_for_nonexistent_property(): void

/** @test */
public function it_should_create_property_when_owner_provides_valid_data(): void
// POST /api/v1/properties → 201, propriété en base avec user_id correct

/** @test */
public function it_should_return_422_when_required_fields_are_missing(): void
// title, price, type, city sont requis

/** @test */
public function it_should_update_property_when_owner_sends_valid_patch(): void
// PATCH /api/v1/properties/{id} → 200, champs mis à jour

/** @test */
public function it_should_return_403_when_non_owner_tries_to_update_property(): void
// User B tente de modifier la propriété de User A → 403

/** @test */
public function it_should_return_403_when_non_owner_tries_to_delete_property(): void

/** @test */
public function it_should_soft_delete_property_and_not_expose_it_in_listings(): void
// Après DELETE, propriété absente de GET /properties mais présente avec withTrashed

/** @test */
public function it_should_not_expose_draft_properties_to_unauthenticated_users(): void
// status=draft → invisible sans auth

/** @test */
public function it_should_return_only_owner_properties_in_my_listings_endpoint(): void
// GET /api/v1/my-properties → uniquement les propriétés de l'utilisateur connecté
```

**Fichier :** `tests/Feature/Property/PropertySearchTest.php`

```php
/** @test */
public function it_should_return_properties_sorted_by_price_ascending(): void

/** @test */
public function it_should_return_properties_sorted_by_date_descending(): void

/** @test */
public function it_should_search_properties_by_keyword_in_title_and_description(): void
// GET /api/v1/properties?q=bastos → résultats contenant 'bastos'

/** @test */
public function it_should_sanitize_search_query_to_prevent_sql_injection(): void
// q='; DROP TABLE properties; -- → pas d'erreur, résultats normaux

/** @test */
public function it_should_return_properties_within_radius_of_coordinates(): void
// lat=3.848&lng=11.502&radius=5 (km) → uniquement propriétés dans le rayon

/** @test */
public function it_should_not_expose_owner_personal_data_in_listing_response(): void
// Email, téléphone de l'owner ne doivent PAS apparaître dans /properties (liste)
```

### 2.2 Frontend

**Fichier :** `tests/components/PropertyCard.test.tsx`

```typescript
describe('PropertyCard', () => {

  const mockProperty = {
    id: 1, title: 'Appartement T3 Bastos',
    price: 150000, currency: 'XAF', type: 'apartment',
    city: 'Yaoundé', images: [{ url: '/img/test.jpg', alt: 'Vue salon' }],
    status: 'available',
  };

  it('should render property title and formatted price', () => {
    render(<PropertyCard property={mockProperty} />);
    expect(screen.getByText('Appartement T3 Bastos')).toBeInTheDocument();
    expect(screen.getByText(/150 000/)).toBeInTheDocument();
    expect(screen.getByText(/FCFA/i)).toBeInTheDocument();
  });

  it('should show unavailable badge when status is rented', () => {
    render(<PropertyCard property={{ ...mockProperty, status: 'rented' }} />);
    expect(screen.getByText(/loué/i)).toBeInTheDocument();
  });

  it('should not show pay button when property is already rented', () => {
    render(<PropertyCard property={{ ...mockProperty, status: 'rented' }} />);
    expect(screen.queryByRole('button', { name: /payer/i })).not.toBeInTheDocument();
  });

  it('should have a link to the property detail page', () => {
    render(<PropertyCard property={mockProperty} />);
    expect(screen.getByRole('link')).toHaveAttribute('href', '/properties/1');
  });

  it('should not expose owner email or phone in the rendered DOM', () => {
    render(<PropertyCard property={{ ...mockProperty, owner: { email: 'owner@test.com', phone: '+237699000000' } } as any} />);
    expect(document.body.innerHTML).not.toContain('owner@test.com');
    expect(document.body.innerHTML).not.toContain('+237699000000');
  });
});
```

---

## 🗂️ MODULE 3 — PAIEMENTS (déjà couvert — référence)

> Voir `PROMPT_TESTS_SECURITE_PAIEMENT.md` pour la suite complète.
> Rappel des scénarios clés à toujours inclure :
> - Idempotence webhook (même event reçu 3x → 1 seul traitement)
> - Amount mismatch (montant gateway ≠ montant BDD → rejet)
> - Signature invalide webhook → 401, rien en base
> - Statut success non rétrogradable via webhook falsifié

---

## 🗂️ MODULE 4 — RÉSERVATIONS / VISITES

### 4.1 Backend

**Fichier :** `tests/Feature/Booking/BookingTest.php`

```php
/** @test */
public function it_should_create_booking_when_slot_is_available(): void
// POST /api/v1/bookings → 201, booking en base, status=pending

/** @test */
public function it_should_return_409_when_slot_is_already_booked(): void
// Même créneau demandé → 409 Conflict, pas de double booking

/** @test */
public function it_should_return_409_when_property_is_not_available(): void
// Propriété status=rented → impossible de réserver

/** @test */
public function it_should_return_422_when_booking_date_is_in_the_past(): void

/** @test */
public function it_should_send_confirmation_notification_after_booking_created(): void
// Notification::fake(); ... Notification::assertSentTo($user, BookingConfirmed::class)

/** @test */
public function it_should_allow_owner_to_confirm_booking(): void
// PATCH /api/v1/bookings/{id}/confirm par l'owner → status=confirmed

/** @test */
public function it_should_return_403_when_non_owner_tries_to_confirm_booking(): void

/** @test */
public function it_should_allow_user_to_cancel_their_own_booking(): void
// PATCH /api/v1/bookings/{id}/cancel → status=cancelled

/** @test */
public function it_should_return_403_when_user_tries_to_cancel_another_users_booking(): void

/** @test */
public function it_should_not_expose_other_users_bookings_in_list(): void
// GET /api/v1/bookings → seulement les bookings de l'utilisateur connecté

/** @test */
public function it_should_return_available_slots_for_a_property(): void
// GET /api/v1/properties/{id}/slots → créneaux disponibles

/** @test */
public function it_should_prevent_double_booking_under_concurrent_requests(): void
// Simuler deux requêtes simultanées pour le même créneau → une seule réussit
// Utiliser des DB transactions et locks pessimistes (lockForUpdate)
```

### 4.2 Frontend

```typescript
describe('BookingForm', () => {

  it('should show available time slots for selected date', async () => { })

  it('should disable already booked slots', async () => { })

  it('should show confirmation dialog before submitting', async () => { })

  it('should show success message after booking is confirmed', async () => { })

  it('should not allow booking in the past via date picker manipulation', async () => {
    // Tenter de soumettre une date passée via manipulation du DOM
    // Le formulaire ne doit pas soumettre
  });
});
```

---

## 🗂️ MODULE 5 — MESSAGERIE / CHAT

### 5.1 Backend

**Fichier :** `tests/Feature/Messaging/MessageTest.php`

```php
/** @test */
public function it_should_create_conversation_between_buyer_and_owner(): void
// POST /api/v1/conversations → 201

/** @test */
public function it_should_not_create_duplicate_conversation_between_same_users(): void
// Même paire user_a/user_b → retourne la conversation existante (idempotent)

/** @test */
public function it_should_send_message_in_existing_conversation(): void
// POST /api/v1/conversations/{id}/messages → 201

/** @test */
public function it_should_return_403_when_user_sends_message_to_conversation_they_dont_belong_to(): void

/** @test */
public function it_should_paginate_messages_in_a_conversation(): void
// GET /api/v1/conversations/{id}/messages?page=2

/** @test */
public function it_should_mark_messages_as_read_when_user_opens_conversation(): void
// PATCH /api/v1/conversations/{id}/read → messages non lus marqués comme lus

/** @test */
public function it_should_not_expose_other_users_conversations(): void
// GET /api/v1/conversations → uniquement les conversations de l'utilisateur connecté

/** @test */
public function it_should_sanitize_message_content_to_prevent_xss(): void
// Contenu : '<script>alert("xss")</script>' → doit être sanitisé avant stockage

/** @test */
public function it_should_reject_message_exceeding_max_length(): void
// Message > 5000 caractères → 422

/** @test */
public function it_should_not_allow_sending_messages_to_blocked_users(): void
```

---

## 🗂️ MODULE 6 — NOTIFICATIONS

**Fichier :** `tests/Feature/Notification/NotificationTest.php`

```php
/** @test */
public function it_should_send_booking_confirmation_notification_to_user(): void
// Notification::fake(); Créer booking → assertSentTo($user, BookingConfirmed::class)

/** @test */
public function it_should_send_payment_success_notification_after_transaction_succeeds(): void

/** @test */
public function it_should_send_new_message_notification_when_message_is_received(): void

/** @test */
public function it_should_not_send_notification_when_user_has_disabled_that_type(): void
// User a désactivé les notifs de message → Notification::assertNotSentTo(...)

/** @test */
public function it_should_mark_notification_as_read_when_user_clicks_it(): void
// PATCH /api/v1/notifications/{id}/read → read_at set

/** @test */
public function it_should_return_only_authenticated_user_notifications(): void

/** @test */
public function it_should_return_unread_count_in_notifications_list(): void
// GET /api/v1/notifications → inclut meta.unread_count

/** @test */
public function it_should_not_expose_other_users_notification_content(): void
```

---

## 🗂️ MODULE 7 — DASHBOARD ADMIN

### 7.1 Backend — Autorisation Admin

**Fichier :** `tests/Feature/Admin/AdminAuthorizationTest.php`

```php
/** @test */
public function it_should_return_403_when_regular_user_accesses_admin_endpoints(): void
// User avec role=user tente GET /api/v1/admin/* → 403 sur tous les endpoints

/** @test */
public function it_should_return_200_when_admin_accesses_admin_dashboard(): void
// User avec role=admin → accès autorisé

/** @test */
public function it_should_return_403_when_agent_accesses_super_admin_endpoints(): void
// Rôles : user < agent < admin < super_admin
// Un agent ne peut pas accéder aux actions super_admin

/** @test */
public function it_should_not_expose_other_users_private_data_in_admin_list(): void
// L'admin voit les données mais SANS les champs ultra-sensibles (tokens, etc.)

/** @test */
public function it_should_log_all_admin_actions_in_audit_table(): void
// Chaque action admin (update status, delete, ban) crée une entrée dans audit_logs

/** @test */
public function it_should_return_correct_dashboard_statistics(): void
// GET /api/v1/admin/stats → { total_users, total_properties, total_revenue, ... }

/** @test */
public function it_should_allow_admin_to_suspend_a_user_account(): void
// PATCH /api/v1/admin/users/{id}/suspend → user.status = suspended

/** @test */
public function it_should_prevent_suspended_user_from_accessing_api(): void
// User suspendu → 403 sur toutes les routes protégées

/** @test */
public function it_should_allow_admin_to_approve_property_listing(): void

/** @test */
public function it_should_prevent_admin_from_suspending_another_admin(): void
// Super admin uniquement peut agir sur un admin
```

---

## 🗂️ MODULE 8 — GÉOLOCALISATION / CARTE

**Fichier :** `tests/Feature/Location/GeoTest.php`

```php
/** @test */
public function it_should_return_properties_within_given_radius_in_km(): void
// Utiliser les coordonnées GPS réelles de Yaoundé/Douala pour les fixtures

/** @test */
public function it_should_validate_that_latitude_is_within_valid_range(): void
// lat > 90 ou lat < -90 → 422

/** @test */
public function it_should_validate_that_longitude_is_within_valid_range(): void
// lng > 180 ou lng < -180 → 422

/** @test */
public function it_should_return_empty_array_when_no_properties_in_radius(): void

/** @test */
public function it_should_not_expose_exact_owner_address_in_public_listing(): void
// Adresse arrondie à la rue, pas au numéro exact

/** @test */
public function it_should_store_coordinates_correctly_when_property_is_created(): void
// Vérifier précision stockage (6 décimales)
```

**Frontend — Tests carte Mapbox**

```typescript
describe('PropertyMap', () => {

  it('should render map container', () => {
    // Mock mapbox-gl pour éviter les erreurs WebGL en jsdom
    vi.mock('mapbox-gl', () => ({ Map: vi.fn(() => ({ on: vi.fn(), addSource: vi.fn(), addLayer: vi.fn(), remove: vi.fn() })) }));
    render(<PropertyMap properties={[]} />);
    expect(screen.getByTestId('map-container')).toBeInTheDocument();
  });

  it('should not expose Mapbox token in rendered DOM', () => {
    render(<PropertyMap properties={[]} />);
    expect(document.body.innerHTML).not.toContain(process.env.NEXT_PUBLIC_MAPBOX_TOKEN ?? 'mapbox_token');
  });
});
```

---

## 🗂️ MODULE 9 — UPLOAD DE FICHIERS / IMAGES

### 9.1 Backend

**Fichier :** `tests/Feature/Upload/FileUploadTest.php`

```php
/** @test */
public function it_should_upload_image_successfully_when_file_is_valid_jpeg(): void
// POST /api/v1/uploads → 201, fichier stocké dans storage/fake

/** @test */
public function it_should_return_422_when_file_exceeds_max_size(): void
// Fichier > 5MB → 422 avec message explicite

/** @test */
public function it_should_return_422_when_file_type_is_not_allowed(): void
// Upload d'un .exe, .php, .sh → 422

/** @test */
public function it_should_return_422_when_file_has_valid_extension_but_wrong_mime_type(): void
// Fichier .jpg qui est en réalité un .php → détection MIME réelle, pas juste extension

/** @test */
public function it_should_strip_exif_metadata_from_uploaded_images(): void
// GPS coordinates dans les EXIF → doivent être supprimées avant stockage

/** @test */
public function it_should_generate_unique_filename_to_prevent_overwrite(): void
// Deux uploads du même fichier → deux noms différents

/** @test */
public function it_should_return_403_when_user_tries_to_delete_another_users_file(): void

/** @test */
public function it_should_soft_delete_file_record_when_property_is_deleted(): void
// Les fichiers en storage restent (backup) mais les records sont soft-deleted

/** @test */
public function it_should_reject_upload_with_path_traversal_in_filename(): void
// filename = '../../config/app.php' → 422 ou sanitisé

/** @test */
public function it_should_limit_number_of_images_per_property(): void
// Max 20 images par propriété → la 21ème → 422
```

---

## 🗂️ MODULE 10 — ABONNEMENTS / PLANS

**Fichier :** `tests/Feature/Subscription/SubscriptionTest.php`

```php
/** @test */
public function it_should_limit_property_listings_based_on_free_plan(): void
// Plan gratuit : max 2 annonces → la 3ème → 403 avec upgrade prompt

/** @test */
public function it_should_allow_unlimited_listings_on_premium_plan(): void

/** @test */
public function it_should_restrict_featured_listings_to_premium_subscribers(): void
// User gratuit tente de mettre en avant une annonce → 403

/** @test */
public function it_should_activate_premium_features_after_successful_payment(): void
// Transaction success → abonnement activé, features déverrouillées

/** @test */
public function it_should_deactivate_premium_features_when_subscription_expires(): void
// subscription.expires_at < now() → retour aux limites du plan gratuit

/** @test */
public function it_should_send_renewal_reminder_7_days_before_expiry(): void
// Command/Job testé avec Notification::fake()

/** @test */
public function it_should_not_allow_user_to_have_two_active_subscriptions(): void
```

---

## 🗂️ SÉCURITÉ GLOBALE

### Fichier : `tests/Feature/Security/GlobalSecurityTest.php`

```php
// ─── INJECTION & MANIPULATION ─────────────────────────────────────────────

/** @test */
public function it_should_return_404_not_500_when_sql_injection_is_attempted_in_url(): void
// GET /api/v1/properties/' OR 1=1 -- → 404, pas d'erreur SQL exposée

/** @test */
public function it_should_not_expose_stack_traces_in_production_error_responses(): void
// APP_ENV=production → les erreurs 500 retournent uniquement { message: 'Server Error' }

/** @test */
public function it_should_sanitize_all_string_inputs_against_xss(): void
// title = '<img src=x onerror=alert(1)>' → stocké sanitisé

/** @test */
public function it_should_reject_requests_with_oversized_payloads(): void
// Payload > 10MB → 413

// ─── HEADERS DE SÉCURITÉ ──────────────────────────────────────────────────

/** @test */
public function it_should_return_security_headers_on_all_responses(): void
{
    $response = $this->getJson('/api/v1/properties');

    $response->assertHeader('X-Content-Type-Options', 'nosniff');
    $response->assertHeader('X-Frame-Options');
    $response->assertHeader('Strict-Transport-Security');
    $response->assertHeader('Referrer-Policy');
}

/** @test */
public function it_should_not_expose_laravel_version_in_response_headers(): void
{
    $response = $this->getJson('/api/v1/properties');
    $response->assertHeaderMissing('X-Powered-By');
    $this->assertNull($response->headers->get('Server'));
}

// ─── IDOR (Insecure Direct Object Reference) ──────────────────────────────

/** @test */
public function it_should_not_expose_sequential_ids_that_allow_enumeration(): void
// Les IDs exposés dans l'API doivent être UUIDs ou obfusqués, pas 1, 2, 3...

/** @test */
public function it_should_return_404_instead_of_403_to_prevent_resource_enumeration(): void
// User B tente d'accéder à une ressource de User A → 404 (pas 403 qui révèle l'existence)

// ─── MASS ASSIGNMENT ──────────────────────────────────────────────────────

/** @test */
public function it_should_ignore_role_field_sent_by_user_in_registration(): void
// POST avec { role: 'admin' } → role restera 'user'

/** @test */
public function it_should_ignore_is_verified_field_sent_by_client(): void

/** @test */
public function it_should_ignore_subscription_status_field_sent_by_client(): void

// ─── RATE LIMITING ────────────────────────────────────────────────────────

/** @test */
public function it_should_rate_limit_api_at_60_requests_per_minute(): void

/** @test */
public function it_should_rate_limit_auth_endpoints_more_aggressively(): void
// /api/auth/* → max 10 requêtes/minute

/** @test */
public function it_should_return_retry_after_header_when_rate_limited(): void
// Header Retry-After présent dans la réponse 429

// ─── DONNÉES SENSIBLES ────────────────────────────────────────────────────

/** @test */
public function it_should_never_expose_user_password_hash_in_any_api_response(): void

/** @test */
public function it_should_never_expose_payment_secret_keys_in_any_api_response(): void

/** @test */
public function it_should_never_expose_clerk_secret_key_in_any_api_response(): void

/** @test */
public function it_should_truncate_sensitive_data_in_application_logs(): void
// Les logs ne doivent pas contenir de tokens, clés API, ou numéros de carte
```

### Fichier : `tests/Feature/Security/GlobalSecurityFrontend.test.tsx`

```typescript
describe('Sécurité globale frontend', () => {

  it('should not include any secret env variables in client bundle', () => {
    // Vérifier que NEXT_PUBLIC_ ne contient pas de données sensibles
    const publicEnvKeys = Object.keys(process.env).filter(k => k.startsWith('NEXT_PUBLIC_'));
    publicEnvKeys.forEach(key => {
      expect(process.env[key]).not.toMatch(/secret/i);
      expect(process.env[key]).not.toMatch(/private/i);
      expect(process.env[key]).not.toMatch(/FLWSECK/);
    });
  });

  it('should sanitize user input before display to prevent XSS', async () => {
    const maliciousInput = '<script>window.__hacked = true</script>';
    render(<PropertyTitle title={maliciousInput} />);
    expect(document.querySelector('script')).not.toBeInTheDocument();
    expect((window as any).__hacked).toBeUndefined();
  });

  it('should redirect on auth error from API (401)', async () => {
    server.use(http.get('/api/properties', () => HttpResponse.json({ message: 'Unauthenticated' }, { status: 401 })));
    // Vérifier que l'intercepteur axios/fetch redirige vers /sign-in
  });

  it('should not store sensitive data in localStorage', () => {
    // Après login, vérifier que localStorage ne contient pas de tokens secrets
    const keys = Object.keys(localStorage);
    keys.forEach(key => {
      const value = localStorage.getItem(key) ?? '';
      expect(value).not.toMatch(/FLWSECK/);
      expect(value).not.toMatch(/sk_live/);
    });
  });

  it('should have Content-Security-Policy meta tag or header', () => {
    // Vérifié via next.config.ts — s'assurer que la balise est présente
  });
});
```

---

## 🗂️ INTÉGRITÉ DES DONNÉES

### Fichier : `tests/Feature/DataIntegrity/DataIntegrityTest.php`

```php
/** @test */
public function it_should_rollback_transaction_when_payment_creation_fails_midway(): void
// Simuler une exception après création Transaction mais avant appel gateway
// Vérifier : aucune transaction orpheline en base

/** @test */
public function it_should_maintain_referential_integrity_when_user_is_deleted(): void
// Soft-delete user → ses propriétés, bookings, messages sont cascade-soft-deleted

/** @test */
public function it_should_not_allow_booking_amount_to_differ_from_property_price(): void
// Tenter de créer une transaction avec amount ≠ property.price → 422

/** @test */
public function it_should_prevent_property_from_being_booked_twice_simultaneously(): void
// Test avec lockForUpdate / DB transactions

/** @test */
public function it_should_maintain_consistent_state_after_failed_file_upload(): void
// Upload partiel → aucun record en base, fichier temporaire nettoyé

/** @test */
public function it_should_keep_audit_trail_for_all_status_changes(): void
// Chaque changement de status (property, booking, transaction) loggé dans audit_logs

/** @test */
public function it_should_not_delete_transaction_history_when_property_is_deleted(): void
// Données financières = données légales → jamais supprimées (uniquement soft-delete)
```

---

## 🗂️ TESTS E2E PLAYWRIGHT — FLUX CRITIQUES

### Configuration

```typescript
// playwright.config.ts
export default defineConfig({
  testDir:       './e2e',
  fullyParallel: false,
  retries:       process.env.CI ? 2 : 0,
  use: {
    baseURL: process.env.E2E_BASE_URL || 'http://localhost:3000',
    trace:   'on-first-retry',
    video:   'on-first-retry',
  },
  projects: [
    { name: 'desktop', use: { ...devices['Desktop Chrome'] } },
    { name: 'mobile',  use: { ...devices['Pixel 5'] } },
  ],
});
```

### Flux E2E prioritaires

```typescript
// e2e/flows/

// 1. Inscription → Profil complété → Première annonce publiée
// e2e/flows/onboarding.spec.ts

// 2. Recherche propriété → Voir détail → Réserver une visite
// e2e/flows/search-and-book.spec.ts

// 3. Paiement complet MTN → Confirmation → Reçu
// e2e/flows/payment-mtn.spec.ts

// 4. Propriétaire reçoit réservation → Confirme → Locataire notifié
// e2e/flows/booking-owner-flow.spec.ts

// 5. Admin suspend un utilisateur → Utilisateur perd accès
// e2e/flows/admin-suspend.spec.ts

// 6. Upload images propriété → Vérification affichage
// e2e/flows/property-images.spec.ts
```

**Exemple complet :**

```typescript
// e2e/flows/search-and-book.spec.ts
test.describe('Recherche et réservation', () => {

  test('should find property and book a visit', async ({ page }) => {
    await loginAsUser(page, 'tenant@keyhome.test');

    // 1. Recherche
    await page.goto('/properties');
    await page.fill('[data-testid="search-input"]', 'Douala');
    await page.click('[data-testid="search-btn"]');
    await expect(page.locator('[data-testid="property-card"]').first()).toBeVisible();

    // 2. Accéder au détail
    await page.locator('[data-testid="property-card"]').first().click();
    await expect(page.locator('h1')).toBeVisible();

    // 3. Réserver une visite
    await page.click('[data-testid="book-visit-btn"]');
    await expect(page.locator('[role="dialog"]')).toBeVisible();
    await page.click('[data-testid="slot-2025-03-10-10h"]');
    await page.click('[data-testid="confirm-booking-btn"]');

    // 4. Vérifier confirmation
    await expect(page.locator('[data-testid="booking-success"]')).toBeVisible();
    await expect(page.locator('[data-testid="booking-reference"]')).toBeVisible();
  });

  test('should not allow booking the same slot twice', async ({ page }) => {
    // Deux utilisateurs tentent de réserver le même créneau
    // Le second doit recevoir un message "créneau indisponible"
  });
});
```

---

## 🗂️ FACTORIES GLOBALES

```php
// Créer les factories pour tous les modèles :

UserFactory::class          // roles: user, agent, admin, super_admin
PropertyFactory::class      // statuses: draft, available, rented, sold
BookingFactory::class       // statuses: pending, confirmed, cancelled, completed
MessageFactory::class
ConversationFactory::class
NotificationFactory::class
SubscriptionFactory::class  // plans: free, basic, premium
FileFactory::class          // types: image, document
AuditLogFactory::class
```

---

## 🗂️ PIPELINE CI/CD COMPLET

```yaml
# .github/workflows/full-test-suite.yml
name: Full Test Suite

on:
  push:
    branches: [main, develop]
  pull_request:

jobs:

  backend:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - uses: shivammathur/setup-php@v2
        with: { php-version: '8.3', coverage: xdebug }
      - run: composer install --no-interaction
      - run: cp .env.testing .env && php artisan key:generate
      - run: php artisan test --coverage --min=85 --parallel
      - run: composer audit                              # Vulnérabilités dépendances
      - name: Upload coverage
        uses: codecov/codecov-action@v4

  frontend:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - uses: actions/setup-node@v4
        with: { node-version: '20' }
      - run: npm ci
      - run: npm run test:coverage -- --reporter=verbose
      - run: npm audit --audit-level=high              # Vulnérabilités npm
      - name: Check for secret leaks in bundle
        run: |
          npm run build
          ! grep -r "FLWSECK\|sk_live\|sk_sandbox" .next/static/ && echo "✅ No secrets in bundle"

  e2e:
    runs-on: ubuntu-latest
    needs: [backend, frontend]
    steps:
      - uses: actions/checkout@v4
      - uses: actions/setup-node@v4
        with: { node-version: '20' }
      - run: npm ci
      - run: npx playwright install --with-deps chromium
      - run: npm run e2e
      - uses: actions/upload-artifact@v4
        if: failure()
        with:
          name: playwright-report
          path: playwright-report/

  security-scan:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - name: Run Trivy vulnerability scanner
        uses: aquasecurity/trivy-action@master
        with:
          scan-type: 'fs'
          severity: 'CRITICAL,HIGH'
```

---

## ✅ CHECKLIST FINALE AVANT MERGE

**Par module :**
- [ ] Auth : webhook Clerk validé, Sanctum token validé, routes protégées testées
- [ ] Propriétés : CRUD complet, isolation données, search sanitisé
- [ ] Paiements : idempotence, signature webhook, amount mismatch, pas de downgrade
- [ ] Réservations : double booking, concurrence, isolation
- [ ] Messagerie : XSS sanitisé, isolation conversations, rate limit
- [ ] Notifications : envoi conditionnel, isolation, préférences respectées
- [ ] Admin : RBAC complet, audit log, suspension
- [ ] Géolocalisation : coordonnées valides, adresse non exposée exactement
- [ ] Uploads : MIME check, taille, path traversal, EXIF strip
- [ ] Abonnements : limites plan respectées, expiration

**Sécurité globale :**
- [ ] Zéro clé secrète dans les réponses API
- [ ] Zéro clé secrète dans le bundle Next.js
- [ ] Headers de sécurité présents sur toutes les réponses
- [ ] IDOR → 404 (pas 403) sur ressources d'autres utilisateurs
- [ ] Mass assignment bloqué sur tous les champs sensibles (role, status, is_verified)
- [ ] Rate limiting actif sur tous les endpoints publics
- [ ] Stack traces absentes des réponses d'erreur en production

---

*Prompt généré pour le projet KeyHome — Tests complets tous modules*
*Stack : Laravel · Next.js · Flutter · Clerk · Sanctum*
