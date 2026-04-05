# KeyHome — Backend Layer Architecture

> **Convention source:** `AGENTS.md` + `CLAUDE.md`
> **Last updated:** April 2026

---

## Layer Map

```
HTTP Request
    │
    ▼
┌──────────────────────────────────────────┐
│  CONTROLLER  app/Http/Controllers/Api/V1/ │
│  final class, no business logic           │
│  Delegates to FormRequest + Service/Action│
└──────────────────────┬───────────────────┘
                       │
          ┌────────────┴──────────────┐
          ▼                           ▼
┌─────────────────┐       ┌──────────────────────┐
│   FORM REQUEST  │       │   ACTION / SERVICE   │
│ app/Http/Requests│       │ app/Actions/         │
│ Validates input │       │ app/Services/        │
│ Authorizes via  │       │ Business logic       │
│ Policy          │       │ final readonly class │
└─────────────────┘       └──────────┬───────────┘
                                     │
                    ┌────────────────┼──────────────────┐
                    ▼                ▼                   ▼
             ┌────────────┐  ┌────────────┐   ┌──────────────┐
             │   MODEL    │  │    DTO     │   │   EVENT      │
             │ app/Models/│  │ app/DTOs/  │   │ app/Events/  │
             │ Eloquent   │  │ Immutable  │   │ → Listeners  │
             │ UUID, soft │  │ value obj  │   │ (async queue)│
             └────────────┘  └────────────┘   └──────────────┘
```

---

## Layer Rules

### Controllers (`app/Http/Controllers/Api/V1/`)

```php
final class AdController extends Controller
{
    public function __construct(
        private readonly AdService $adService,   // DI only
    ) {}

    public function store(StoreAdRequest $request): JsonResponse
    {
        // 1. FormRequest already validated & authorized
        // 2. Call action or service — no business logic here
        $ad = (new CreateAd($this->adService))->handle($request->validated());

        return ApiResponse::success($ad, 201);
    }
}
```

**Rules:**
- `final` — no inheritance
- Constructor DI only — services injected, not resolved inline
- Zero `if`/`foreach` business logic — delegate to Service/Action
- Return via `ApiResponse::success()` or `ApiResponse::error()`
- One method per HTTP verb per resource

---

### Services (`app/Services/`)

```php
final readonly class AdService
{
    public function __construct(
        private AdRepository $repository,
        private MediaService $media,
    ) {}

    public function publish(Ad $ad, User $author): Ad
    {
        // All business rules live here
        throw_unless($author->canPublish(), AdPublishingDeniedException::class);
        $ad->transitionTo(AdStatus::Pending);
        event(new AdCreated($ad));
        return $ad;
    }
}
```

**Rules:**
- `final readonly` — immutable, no subclassing
- Never return `JsonResponse` — return domain objects or throw exceptions
- Never access `Request` directly — receive plain values or DTOs
- `throw` domain exceptions, not HTTP exceptions

---

### Actions (`app/Actions/`)

Single-purpose, orchestrate one specific use-case:

```php
final class CreateAd
{
    public function __construct(
        private readonly AdService $adService,
    ) {}

    public function handle(array $data): Ad
    {
        return $this->adService->create($data);
    }
}
```

---

### DTOs (`app/DTOs/`)

Immutable value objects — used as service return types:

```php
final readonly class LoginResult
{
    public function __construct(
        public readonly User $user,
        public readonly string $token,
        public readonly bool $requiresMfa,
    ) {}
}
```

---

### Models (`app/Models/`)

```php
class Ad extends Model
{
    use HasUuids, SoftDeletes, HasMedia;

    // Status NEVER in $fillable — use transitionTo()
    protected $fillable = ['title', 'description', 'price', ...];

    // Spatial column (PostGIS)
    protected $casts = ['location' => PointCast::class];

    // Scopes
    public function scopeAvailable(Builder $q): Builder { ... }
}
```

**Rules:**
- `HasUuids` — UUID primary key on all models
- `SoftDeletes` — never hard-delete key entities
- `HasMedia` (Spatie) — media attached via Media Library
- Status fields use state machine methods (`transitionTo()`), never raw `update(['status' => ...])`)
- `preventLazyLoading()` active in dev — always eager-load

---

## Naming Conventions

| Layer | Class suffix | Example |
|-------|-------------|---------|
| Controller | `Controller` | `AdController` |
| Service | `Service` | `AdBoostService` |
| Action | `Action` (or verb noun) | `CreateAd`, `HandlePostPaymentActions` |
| Form Request | `Request` | `StoreAdRequest`, `UpdateUserRequest` |
| DTO | `Result` or `Data` | `LoginResult`, `RegistrationResult` |
| Event | Past tense noun | `AdCreated`, `PaymentSucceeded` |
| Listener | Present tense verb | `AutoBoostNewAd`, `NotifyOwnerOfStatusChange` |
| Job | Verb noun + `Job` | `MatchSearchAlertsForAdJob` |
| Policy | `Policy` | `AdPolicy`, `UserPolicy` |

---

## Error Handling

```
Service throws domain exception
         │
         ▼
bootstrap/app.php withExceptions() handlers
         │
         ├── ModelNotFoundException    → 404 JSON
         ├── AuthorizationException   → 403 JSON
         ├── AuthenticationException  → 401 JSON
         ├── ThrottleRequestsException→ 429 JSON
         └── Throwable                → 500 JSON (Sentry captures)
```

All API errors follow this envelope:
```json
{
  "success": false,
  "message": "Human-readable message",
  "code": "ERROR_CODE"
}
```

**Never** return:
- `error` key with stack trace
- 422 for database/server errors
- HTML 404/403 on API routes

---

## Adding a New Endpoint — Checklist

- [ ] Create `FormRequest` in `app/Http/Requests/`
- [ ] Add `Policy` method if needed
- [ ] Business logic in `Service` or `Action` (not controller)
- [ ] Return DTO or Eloquent model (not `JsonResponse` from service)
- [ ] Return `ApiResponse::success()` in controller
- [ ] Add route to `routes/api/*.php`
- [ ] Write Pest feature test covering happy path + auth failure + validation failure
- [ ] Run `php artisan test` — all tests must pass
- [ ] Run `vendor/bin/phpstan analyse` — no new errors
- [ ] Run `vendor/bin/pint` — code style clean
