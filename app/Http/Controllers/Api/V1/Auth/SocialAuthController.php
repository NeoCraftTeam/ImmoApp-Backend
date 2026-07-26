<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Enums\UserRole;
use App\Http\Requests\Api\V1\SocialAuthRequest;
use App\Mail\OAuthLinkAttemptMail;
use App\Models\User;
use App\Services\Auth\TokenService;
use App\Services\UtmAttributionService;
use App\Support\FrontendRedirectGuard;
use App\Support\OAuthProviderAvailability;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use OpenApi\Attributes as OA;

/**
 * Controller for OAuth social authentication.
 *
 * Supports: Google, Facebook, Apple
 * Usage: Mobile apps and SPAs send OAuth tokens directly
 */
#[OA\Tag(name: 'OAuth', description: 'Social authentication endpoints')]
final readonly class SocialAuthController
{
    public function __construct(private TokenService $tokenService) {}

    /**
     * Supported OAuth providers.
     *
     * @var array<string>
     */
    private const array SUPPORTED_PROVIDERS = ['google', 'facebook', 'apple', 'github'];

    /**
     * Handle OAuth callback for mobile/SPA apps.
     *
     * Mobile apps get the OAuth token from the provider SDK,
     * then send it to this endpoint to authenticate with our backend.
     */
    #[OA\Post(
        path: '/api/v1/auth/oauth/{provider}',
        summary: 'Authenticate via OAuth provider',
        description: 'Authenticate user with OAuth token from mobile SDK or web flow. Creates account if not exists.',
        tags: ['OAuth'],
        parameters: [
            new OA\Parameter(
                name: 'provider',
                description: 'OAuth provider (google, facebook, apple)',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'string', enum: ['google', 'facebook', 'apple'])
            ),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['token'],
                properties: [
                    new OA\Property(property: 'token', type: 'string', description: 'OAuth access token from provider'),
                    new OA\Property(property: 'id_token', type: 'string', description: 'ID token (required for Apple)'),
                    new OA\Property(property: 'role', type: 'string', enum: ['customer', 'agent'], description: 'User role for new accounts'),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Authentication successful',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'Connexion réussie'),
                        new OA\Property(property: 'user', ref: '#/components/schemas/User'),
                        new OA\Property(property: 'token', type: 'string', example: '1|abc123...'),
                        new OA\Property(property: 'is_new_user', type: 'boolean', example: false),
                    ]
                )
            ),
            new OA\Response(response: 400, description: 'Invalid provider'),
            new OA\Response(response: 401, description: 'Invalid OAuth token'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function authenticate(SocialAuthRequest $request, string $provider): JsonResponse
    {
        // Validate provider
        if (!in_array($provider, self::SUPPORTED_PROVIDERS, true)) {
            return response()->json([
                'message' => 'Provider OAuth non supporté',
                'supported_providers' => self::SUPPORTED_PROVIDERS,
            ], 400);
        }

        $utmPayload = $request->only(UtmAttributionService::ATTRIBUTION_REQUEST_KEYS);

        try {
            // Get user info from OAuth provider
            $socialUser = $this->getSocialUser($provider, $request->token, $request->id_token);

            if (!$socialUser || !$socialUser->getEmail()) {
                return response()->json([
                    'message' => 'Impossible de récupérer les informations utilisateur depuis '.$provider,
                ], 401);
            }

            // Find or create user.
            // NOTE: the requested role is restricted to customer|agent — admin can
            // never be granted via OAuth. The role only applies at CREATION; an
            // existing account keeps its role whatever the client requests.
            $result = $this->findOrCreateUser(
                $socialUser,
                $provider,
                $request,
                $utmPayload,
                $this->sanitizeRequestedRole($request->input('role')),
            );
            $user = $result['user'];
            $isNewUser = $result['is_new'];

            // Cross-provider link requires explicit confirmation
            if ($result['requires_link_confirmation'] ?? false) {
                return response()->json([
                    'message' => 'Un compte existe déjà avec cet email. Confirmez la liaison des comptes.',
                    'requires_link_confirmation' => true,
                    'linking_token' => $result['linking_token'],
                ], 200);
            }

            // Update last login info
            $user->forceFill([
                'last_login_at' => now(),
                'last_login_ip' => $request->ip(),
            ])->save();

            // Create Sanctum token
            $token = $this->tokenService->createForUser($user, 'oauth_'.$provider)->plainTextToken;

            Log::info('OAuth authentication successful', [
                'provider' => $provider,
                'user_id' => $user->id,
                'is_new_user' => $isNewUser,
            ]);

            return response()->json([
                'message' => $isNewUser ? 'Compte créé avec succès' : 'Connexion réussie',
                'user' => $user->load('city'),
                'token' => $token,
                'is_new_user' => $isNewUser,
            ]);

        } catch (Exception $e) {
            Log::error('OAuth authentication failed', [
                'provider' => $provider,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'Échec de l\'authentification. Veuillez réessayer.',
            ], 401);
        }
    }

    /**
     * Get redirect URL for OAuth provider (web flow).
     */
    #[OA\Get(
        path: '/api/v1/auth/oauth/{provider}/redirect',
        summary: 'Get OAuth redirect URL',
        description: 'Returns the OAuth provider authorization URL for web-based authentication flow.',
        tags: ['OAuth'],
        parameters: [
            new OA\Parameter(
                name: 'provider',
                description: 'OAuth provider',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'string', enum: ['google', 'facebook', 'apple'])
            ),
            new OA\Parameter(
                name: 'redirect_uri',
                description: 'Frontend callback URL',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'string')
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Redirect URL',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'redirect_url', type: 'string'),
                    ]
                )
            ),
        ]
    )]
    public function redirect(Request $request, string $provider): JsonResponse
    {
        if (!in_array($provider, self::SUPPORTED_PROVIDERS, true)) {
            return response()->json([
                'message' => 'Provider OAuth non supporté',
            ], 400);
        }

        if (!OAuthProviderAvailability::isSocialiteConfigured($provider)) {
            $hint = $provider === 'google' && OAuthProviderAvailability::isClerkConfigured()
                ? 'Utilisez la connexion Google via Clerk (application mobile).'
                : 'Ce fournisseur OAuth n\'est pas configuré sur le serveur.';

            return response()->json([
                'message' => 'Connexion '.$provider.' indisponible.',
                'code' => 'OAUTH_PROVIDER_NOT_CONFIGURED',
                'hint' => $hint,
            ], 503);
        }

        $requestedRedirect = (string) $request->query(
            'redirect_uri',
            config('app.frontend_url').'/auth/callback',
        );

        if (!FrontendRedirectGuard::isAllowedAbsoluteUrl($requestedRedirect)) {
            if (FrontendRedirectGuard::isMobileAppRequest($request)) {
                return response()->json([
                    'message' => 'URL de retour OAuth non autorisée pour cette application.',
                    'code' => 'OAUTH_REDIRECT_URI_REJECTED',
                ], 422);
            }

            $redirectUri = config('app.frontend_url').'/auth/callback';
        } else {
            $redirectUri = $requestedRedirect;
        }

        // Encode redirect_uri in state parameter (stateless approach for API).
        // The requested role rides along so callback() can create bailleur
        // accounts initiated from the owner panel / owners mobile app.
        $stateData = [
            'csrf' => Str::random(40),
            'redirect_uri' => $redirectUri,
            'role' => $this->sanitizeRequestedRole($request->query('role')),
        ];
        $state = base64_encode(json_encode($stateData) ?: '');

        $driver = Socialite::driver($provider)
            ->stateless() // @phpstan-ignore method.notFound
            ->with(['state' => $state]);

        // Apple requires additional scopes
        if ($provider === 'apple') {
            $driver->scopes(['name', 'email']);
        }

        return response()->json([
            'redirect_url' => $driver->redirect()->getTargetUrl(),
        ]);
    }

    /**
     * Lancer une liaison de provider pour un utilisateur AUTHENTIFIÉ via
     * le flux redirect (le mobile n'a pas de token provider natif pour
     * l'endpoint `link`). Un `link_code` à usage unique est mis en cache
     * (→ id utilisateur) et encodé dans le `state` ; `callback()` le lit
     * pour LIER le provider au compte au lieu d'ouvrir une session.
     */
    public function linkRedirect(Request $request, string $provider): JsonResponse
    {
        if (!in_array($provider, self::SUPPORTED_PROVIDERS, true)) {
            return response()->json(['message' => 'Provider OAuth non supporté'], 400);
        }

        $requestedRedirect = (string) $request->query(
            'redirect_uri',
            config('app.frontend_url').'/auth/callback',
        );

        if (!FrontendRedirectGuard::isAllowedAbsoluteUrl($requestedRedirect)) {
            if (FrontendRedirectGuard::isMobileAppRequest($request)) {
                return response()->json([
                    'message' => 'URL de retour OAuth non autorisée pour cette application.',
                    'code' => 'OAUTH_REDIRECT_URI_REJECTED',
                ], 422);
            }

            $redirectUri = config('app.frontend_url').'/auth/callback';
        } else {
            $redirectUri = $requestedRedirect;
        }

        $linkCode = Str::random(64);
        Cache::put('oauth_link_'.$linkCode, (string) $request->user()->id, now()->addMinutes(10));

        $state = base64_encode(json_encode([
            'csrf' => Str::random(40),
            'redirect_uri' => $redirectUri,
            'link_code' => $linkCode,
        ]) ?: '');

        $driver = Socialite::driver($provider)
            ->stateless() // @phpstan-ignore method.notFound
            ->with(['state' => $state]);
        if ($provider === 'apple') {
            $driver->scopes(['name', 'email']);
        }

        return response()->json(['redirect_url' => $driver->redirect()->getTargetUrl()]);
    }

    /**
     * Rattache le provider au compte identifié par le link_code (issu de
     * linkRedirect). Redirige vers redirect_uri avec `?linked=1` en cas de
     * succès, ou `?link_error=<code>` sinon. Consomme le link_code.
     */
    private function completeLinkFromCallback(
        string $provider,
        string $linkCode,
        mixed $socialUser,
        string $redirectUri,
    ): mixed {
        $userId = Cache::pull('oauth_link_'.$linkCode);
        $sep = str_contains($redirectUri, '?') ? '&' : '?';

        if (!$userId) {
            return redirect($redirectUri.$sep.'link_error=expired&provider='.$provider);
        }

        $user = User::find($userId);
        if (!$user || !$socialUser || !$socialUser->getId()) {
            return redirect($redirectUri.$sep.'link_error=invalid&provider='.$provider);
        }

        $providerIdField = $provider.'_id';
        $alreadyLinked = User::where($providerIdField, $socialUser->getId())
            ->where('id', '!=', $user->id)
            ->exists();
        if ($alreadyLinked) {
            return redirect($redirectUri.$sep.'link_error=already_used&provider='.$provider);
        }

        $user->forceFill([
            $providerIdField => $socialUser->getId(),
            'oauth_provider' => $user->oauth_provider ?? $provider,
        ])->save();

        return redirect($redirectUri.$sep.'linked=1&provider='.$provider);
    }

    /**
     * Handle OAuth callback (web flow).
     */
    #[OA\Get(
        path: '/api/v1/auth/oauth/{provider}/callback',
        summary: 'OAuth callback handler',
        description: 'Handles OAuth provider callback and redirects to frontend with token.',
        tags: ['OAuth'],
        parameters: [
            new OA\Parameter(name: 'provider', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'code', in: 'query', required: true, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'state', in: 'query', schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 302, description: 'Redirect to frontend with token'),
        ]
    )]
    public function callback(Request $request, string $provider): mixed
    {
        if (!in_array($provider, self::SUPPORTED_PROVIDERS, true)) {
            return redirect(config('app.frontend_url').'/auth/error?message=unsupported_provider');
        }

        try {
            // Decode redirect_uri (and requested role / link intent) from
            // state parameter and validate against allowed hosts
            $redirectUri = config('app.frontend_url').'/auth/callback';
            $requestedRole = 'customer';
            $linkCode = null;
            $state = $request->query('state');

            if ($state) {
                $stateData = json_decode(base64_decode($state), true);
                if (is_array($stateData) && isset($stateData['redirect_uri'])) {
                    $candidate = (string) $stateData['redirect_uri'];
                    // Only accept the URI if it matches our allowed hosts whitelist
                    if (FrontendRedirectGuard::isAllowedAbsoluteUrl($candidate)) {
                        $redirectUri = $candidate;
                    } else {
                        Log::warning('OAuth callback: rejected non-whitelisted redirect_uri', [
                            'redirect_uri' => $candidate,
                            'provider' => $provider,
                            'ip' => $request->ip(),
                        ]);
                    }
                }
                if (is_array($stateData) && isset($stateData['role'])) {
                    $requestedRole = $this->sanitizeRequestedRole($stateData['role']);
                }
                if (is_array($stateData) && isset($stateData['link_code'])) {
                    $linkCode = (string) $stateData['link_code'];
                }
            }

            /** @phpstan-ignore method.notFound */
            $socialUser = Socialite::driver($provider)->stateless()->user();

            // Flux de LIAISON authentifiée (initié par linkRedirect) : on
            // rattache le provider au compte identifié par le link_code, au
            // lieu d'ouvrir une session.
            if ($linkCode !== null) {
                return $this->completeLinkFromCallback($provider, $linkCode, $socialUser, $redirectUri);
            }

            $result = $this->findOrCreateUser($socialUser, $provider, $request, [], $requestedRole);
            $user = $result['user'];

            $user->forceFill([
                'last_login_at' => now(),
                'last_login_ip' => $request->ip(),
            ])->save();

            $token = $this->tokenService->createForUser($user, 'oauth_'.$provider)->plainTextToken;

            // Store the real token in cache under a short-lived exchange code.
            // The URL only carries the exchange code — never the raw Sanctum token.
            $exchangeCode = Str::random(64);
            Cache::put('oauth_token_exchange_'.$exchangeCode, [
                'token' => $token,
                'is_new_user' => $result['is_new'],
            ], now()->addMinutes(2));

            return redirect($redirectUri.'?'.http_build_query([
                'exchange_code' => $exchangeCode,
            ]));

        } catch (Exception $e) {
            Log::error('OAuth callback failed', [
                'provider' => $provider,
                'error' => $e->getMessage(),
            ]);

            $redirectUri = config('app.frontend_url').'/auth/error';

            return redirect($redirectUri.'?message=oauth_failed');
        }
    }

    /**
     * Redeem a short-lived OAuth exchange code for a Sanctum token.
     *
     * The frontend receives an exchange_code query param after the OAuth callback
     * redirect and immediately calls this endpoint (within 2 minutes) to get the
     * real token without it ever appearing in browser history or server logs.
     */
    public function exchangeToken(Request $request): JsonResponse
    {
        $request->validate([
            'exchange_code' => ['required', 'string', 'size:64'],
        ]);

        $cacheKey = 'oauth_token_exchange_'.$request->input('exchange_code');
        $data = Cache::pull($cacheKey); // pull = get + delete (single-use)

        if ($data === null) {
            return response()->json([
                'message' => 'Code d\'échange invalide ou expiré.',
                'code' => 'EXCHANGE_CODE_INVALID',
            ], 422);
        }

        return response()->json([
            'token' => $data['token'],
            'is_new_user' => $data['is_new_user'],
        ]);
    }

    /**
     * Link OAuth provider to existing account.
     */
    #[OA\Post(
        path: '/api/v1/auth/oauth/{provider}/link',
        summary: 'Link OAuth provider to account',
        description: 'Links an OAuth provider to the authenticated user\'s account.',
        security: [['sanctum' => []]],
        tags: ['OAuth'],
        parameters: [
            new OA\Parameter(name: 'provider', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['token'],
                properties: [
                    new OA\Property(property: 'token', type: 'string'),
                    new OA\Property(property: 'id_token', type: 'string'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Provider linked successfully'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 409, description: 'Provider already linked to another account'),
        ]
    )]
    public function link(Request $request, string $provider): JsonResponse
    {
        if (!in_array($provider, self::SUPPORTED_PROVIDERS, true)) {
            return response()->json(['message' => 'Provider non supporté'], 400);
        }

        $request->validate([
            'token' => 'required|string',
            'id_token' => 'nullable|string',
        ]);

        try {
            $socialUser = $this->getSocialUser($provider, $request->token, $request->id_token);

            if (!$socialUser) {
                return response()->json(['message' => 'Token OAuth invalide'], 401);
            }

            $providerIdField = $provider.'_id';

            // Check if this OAuth account is already linked to another user
            $existingUser = User::where($providerIdField, $socialUser->getId())->first();
            if ($existingUser && $existingUser->id !== $request->user()->id) {
                return response()->json([
                    'message' => 'Ce compte '.$provider.' est déjà lié à un autre utilisateur',
                ], 409);
            }

            // Link the provider
            $request->user()->update([
                $providerIdField => $socialUser->getId(),
                'oauth_provider' => $provider,
            ]);

            return response()->json([
                'message' => 'Compte '.$provider.' lié avec succès',
                'user' => $request->user()->fresh(),
            ]);

        } catch (Exception $e) {
            Log::error('OAuth link failed', [
                'provider' => $provider,
                'user_id' => $request->user()->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json(['message' => 'Échec de la liaison'], 500);
        }
    }

    /**
     * Unlink OAuth provider from account.
     */
    #[OA\Delete(
        path: '/api/v1/auth/oauth/{provider}/unlink',
        summary: 'Unlink OAuth provider',
        description: 'Removes OAuth provider link from authenticated user\'s account.',
        security: [['sanctum' => []]],
        tags: ['OAuth'],
        parameters: [
            new OA\Parameter(name: 'provider', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Provider unlinked'),
            new OA\Response(response: 400, description: 'Cannot unlink - no password set'),
        ]
    )]
    public function unlink(Request $request, string $provider): JsonResponse
    {
        if (!in_array($provider, self::SUPPORTED_PROVIDERS, true)) {
            return response()->json(['message' => 'Provider non supporté'], 400);
        }

        $user = $request->user();
        $providerIdField = $provider.'_id';

        // Ensure user has a password or another OAuth provider linked
        $hasPassword = !empty($user->password);
        $hasOtherProvider = collect(self::SUPPORTED_PROVIDERS)
            ->filter(fn ($p) => $p !== $provider)
            ->contains(fn ($p) => !empty($user->{$p.'_id'}));

        if (!$hasPassword && !$hasOtherProvider) {
            return response()->json([
                'message' => 'Impossible de délier ce compte. Définissez d\'abord un mot de passe ou liez un autre provider.',
            ], 400);
        }

        $user->update([
            $providerIdField => null,
        ]);

        return response()->json([
            'message' => 'Compte '.$provider.' délié avec succès',
        ]);
    }

    /**
     * Confirm pending cross-provider account link using a short-lived token.
     */
    public function confirmOAuthLink(Request $request): JsonResponse
    {
        $request->validate([
            'linking_token' => ['required', 'string'],
        ]);

        $user = User::where('pending_oauth_token', $request->linking_token)
            ->where('pending_oauth_expires_at', '>', now())
            ->first();

        if (!$user) {
            return response()->json([
                'message' => 'Token de liaison invalide ou expiré.',
            ], 422);
        }

        $providerIdField = $user->pending_oauth_provider.'_id';

        $user->update([
            $providerIdField => $user->pending_oauth_id,
            'oauth_provider' => $user->pending_oauth_provider,
            'oauth_avatar' => $user->pending_oauth_avatar,
            'email_verified_at' => $user->email_verified_at ?? now(),
            'pending_oauth_provider' => null,
            'pending_oauth_id' => null,
            'pending_oauth_avatar' => null,
            'pending_oauth_token' => null,
            'pending_oauth_expires_at' => null,
        ]);

        $token = $this->tokenService->createForUser($user, 'oauth_link')->plainTextToken;

        return response()->json([
            'message' => 'Liaison de compte confirmée avec succès.',
            'user' => $user->fresh()->load('city'),
            'token' => $token,
        ]);
    }

    /**
     * Get social user data from provider.
     */
    private function getSocialUser(string $provider, string $token, ?string $idToken = null): mixed
    {
        $driver = Socialite::driver($provider);

        if ($provider === 'apple' && $idToken) {
            /** @phpstan-ignore method.notFound */
            return $driver->userFromToken($idToken);
        }

        /** @phpstan-ignore method.notFound */
        return $driver->userFromToken($token);
    }

    /**
     * Find existing user or create new one from OAuth data.
     *
     * @return array{user: User, is_new: bool}
     */
    /**
     * @return array{
     *     user: User,
     *     is_new: bool,
     *     requires_link_confirmation?: bool,
     *     linking_token?: string
     * }
     */
    /**
     * @param  array<string, mixed>  $utmPayload
     */
    private function findOrCreateUser(
        mixed $socialUser,
        string $provider,
        Request $request,
        array $utmPayload,
        string $requestedRole = 'customer',
    ): array {
        $providerIdField = $provider.'_id';

        return DB::transaction(function () use ($socialUser, $provider, $providerIdField, $request, $utmPayload, $requestedRole) {
            // Try to find by provider ID first
            $user = User::where($providerIdField, $socialUser->getId())->first();

            if ($user) {
                // Update OAuth avatar if changed
                if ($socialUser->getAvatar() && $user->oauth_avatar !== $socialUser->getAvatar()) {
                    $user->update(['oauth_avatar' => $socialUser->getAvatar()]);
                }

                return ['user' => $user, 'is_new' => false];
            }

            // Try to find by email — requires EXPLICIT confirmation before linking
            $existingUser = User::where('email', $socialUser->getEmail())->first();

            if ($existingUser) {
                $linkingToken = hash('sha256', Str::random(64));
                $existingUser->update([
                    'pending_oauth_provider' => $provider,
                    'pending_oauth_id' => $socialUser->getId(),
                    'pending_oauth_avatar' => $socialUser->getAvatar(),
                    'pending_oauth_token' => $linkingToken,
                    'pending_oauth_expires_at' => now()->addMinutes(5), // Reduced from 15min for security
                ]);

                // Notify the account owner that someone tried to link a provider to their account
                Mail::to($existingUser->email, $existingUser->firstname ?? $existingUser->email)
                    ->queue(new OAuthLinkAttemptMail(
                        userFirstName: $existingUser->firstname ?? 'Utilisateur',
                        provider: $provider,
                        ipAddress: $request->ip() ?? 'inconnu',
                        attemptedAt: now()->translatedFormat('d F Y à H:i'),
                        secureAccountUrl: config('app.frontend_url').'/security/sessions',
                        supportEmail: config('mail.from.address'),
                    ));

                return [
                    'user' => $existingUser,
                    'is_new' => false,
                    'requires_link_confirmation' => true,
                    'linking_token' => $linkingToken,
                ];
            }

            // Create new user with the sanitized requested role (customer par
            // défaut, agent quand le flux vient du panel/app owners — jamais
            // admin). Un agent OAuth démarre en type `individual` ; le
            // rattachement à une agence passe par l'onboarding post-création.
            $names = $this->parseNames($socialUser);

            $utm = app(UtmAttributionService::class);

            $user = new User;
            $user->fill([
                'firstname' => $names['firstname'],
                'lastname' => $names['lastname'],
                'email' => $socialUser->getEmail(),
                'password' => null,
                $providerIdField => $socialUser->getId(),
                'oauth_provider' => $provider,
                'oauth_avatar' => $socialUser->getAvatar(),
                'avatar' => $socialUser->getAvatar() ?? 'avatars/default.png',
            ]);
            $user->forceFill([
                'email_verified_at' => now(),
                'role' => $requestedRole === 'agent' ? UserRole::AGENT : UserRole::CUSTOMER,
                'type' => 'individual',
                'is_active' => true,
                'registration_ip' => $request->ip(),
                'last_login_ip' => $request->ip(),
            ]);
            $user->forceFill($utm->attributesForNewUser($request, $utmPayload));
            $user->save();
            $utm->linkSessionVisitsToUser(
                $user,
                isset($utmPayload['session_id']) && is_string($utmPayload['session_id']) ? $utmPayload['session_id'] : null,
            );

            return ['user' => $user, 'is_new' => true];
        });
    }

    /**
     * Whitelist the role a client may request at OAuth account creation.
     *
     * Only `customer` (default) and `agent` (owner panel / owners app) are
     * accepted — `admin` or any other value silently falls back to customer.
     */
    private function sanitizeRequestedRole(mixed $role): string
    {
        return $role === 'agent' ? 'agent' : 'customer';
    }

    /**
     * Parse first and last names from social user data.
     *
     * @return array{firstname: string, lastname: string}
     */
    private function parseNames(mixed $socialUser): array
    {
        $name = $socialUser->getName() ?? '';
        $parts = explode(' ', $name, 2);

        // Try to get from user array if available
        $user = method_exists($socialUser, 'getRaw') ? $socialUser->getRaw() : [];

        $firstname = $user['given_name'] ?? $user['first_name'] ?? $parts[0];
        $lastname = $user['family_name'] ?? $user['last_name'] ?? ($parts[1] ?? '');

        return [
            'firstname' => $firstname ?: 'Utilisateur',
            'lastname' => $lastname,
        ];
    }
}
