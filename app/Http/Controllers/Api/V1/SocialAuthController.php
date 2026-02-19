<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\UserRole;
use App\Models\User;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
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
final class SocialAuthController
{
    /**
     * Supported OAuth providers.
     *
     * @var array<string>
     */
    private const array SUPPORTED_PROVIDERS = ['google', 'facebook', 'apple'];

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
    public function authenticate(Request $request, string $provider): JsonResponse
    {
        // Validate provider
        if (!in_array($provider, self::SUPPORTED_PROVIDERS, true)) {
            return response()->json([
                'message' => 'Provider OAuth non supporté',
                'supported_providers' => self::SUPPORTED_PROVIDERS,
            ], 400);
        }

        $request->validate([
            'token' => 'required|string',
            'id_token' => 'nullable|string',
            'role' => 'nullable|string|in:customer,agent',
        ]);

        try {
            // Get user info from OAuth provider
            $socialUser = $this->getSocialUser($provider, $request->token, $request->id_token);

            if (!$socialUser || !$socialUser->getEmail()) {
                return response()->json([
                    'message' => 'Impossible de récupérer les informations utilisateur depuis '.$provider,
                ], 401);
            }

            // Find or create user
            $result = $this->findOrCreateUser($socialUser, $provider, $request->role);
            $user = $result['user'];
            $isNewUser = $result['is_new'];

            // Update last login info
            $user->update([
                'last_login_at' => now(),
                'last_login_ip' => $request->ip(),
            ]);

            // Create Sanctum token
            $token = $user->createToken('oauth-'.$provider)->plainTextToken;

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
                'message' => 'Échec de l\'authentification OAuth',
                'error' => config('app.debug') ? $e->getMessage() : 'Une erreur est survenue',
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

        $redirectUri = $request->query('redirect_uri', config('app.frontend_url').'/auth/callback');

        // Encode redirect_uri in state parameter (stateless approach for API)
        $stateData = [
            'csrf' => Str::random(40),
            'redirect_uri' => $redirectUri,
        ];
        $state = base64_encode(json_encode($stateData) ?: '');

        /** @phpstan-ignore method.notFound */
        $driver = Socialite::driver($provider)
            ->stateless()
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
            // Decode redirect_uri from state parameter
            $redirectUri = config('app.frontend_url').'/auth/callback';
            $state = $request->query('state');

            if ($state) {
                $stateData = json_decode(base64_decode($state), true);
                if (is_array($stateData) && isset($stateData['redirect_uri'])) {
                    $redirectUri = $stateData['redirect_uri'];
                }
            }

            /** @phpstan-ignore method.notFound */
            $socialUser = Socialite::driver($provider)->stateless()->user();

            $result = $this->findOrCreateUser($socialUser, $provider);
            $user = $result['user'];

            $user->update([
                'last_login_at' => now(),
                'last_login_ip' => $request->ip(),
            ]);

            $token = $user->createToken('oauth-'.$provider)->plainTextToken;

            return redirect($redirectUri.'?'.http_build_query([
                'token' => $token,
                'is_new_user' => $result['is_new'] ? '1' : '0',
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
    private function findOrCreateUser(mixed $socialUser, string $provider, ?string $role = null): array
    {
        $providerIdField = $provider.'_id';

        return DB::transaction(function () use ($socialUser, $provider, $providerIdField) {
            // Try to find by provider ID first
            $user = User::where($providerIdField, $socialUser->getId())->first();

            if ($user) {
                // Update OAuth avatar if changed
                if ($socialUser->getAvatar() && $user->oauth_avatar !== $socialUser->getAvatar()) {
                    $user->update(['oauth_avatar' => $socialUser->getAvatar()]);
                }

                return ['user' => $user, 'is_new' => false];
            }

            // Try to find by email
            $user = User::where('email', $socialUser->getEmail())->first();

            if ($user) {
                // Link provider to existing account
                $user->update([
                    $providerIdField => $socialUser->getId(),
                    'oauth_provider' => $provider,
                    'oauth_avatar' => $socialUser->getAvatar(),
                    'email_verified_at' => $user->email_verified_at ?? now(),
                ]);

                return ['user' => $user, 'is_new' => false];
            }

            // Create new user
            // Note: OAuth always creates CUSTOMER accounts. Agents require additional setup
            // (type: INDIVIDUAL/AGENCY, agency association) that can't be determined from OAuth.
            // Users can request agent upgrade through the app after completing their profile.
            $names = $this->parseNames($socialUser);

            $user = User::create([
                'firstname' => $names['firstname'],
                'lastname' => $names['lastname'],
                'email' => $socialUser->getEmail(),
                'password' => null, // OAuth users don't need password
                $providerIdField => $socialUser->getId(),
                'oauth_provider' => $provider,
                'oauth_avatar' => $socialUser->getAvatar(),
                'avatar' => $socialUser->getAvatar() ?? 'avatars/default.png',
                'email_verified_at' => now(), // OAuth emails are pre-verified
                'role' => UserRole::CUSTOMER, // Always customer - agents need manual setup
                'is_active' => true,
            ]);

            return ['user' => $user, 'is_new' => true];
        });
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
