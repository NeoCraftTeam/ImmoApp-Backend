<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\DTOs\SocialAuthResult;
use App\Enums\UserRole;
use App\Mail\OAuthLinkAttemptMail;
use App\Models\User;
use App\Services\UtmAttributionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\FacebookProvider;

/**
 * Domain logic for OAuth social authentication: resolving the provider user,
 * finding-or-creating the local account (with the cross-provider link
 * handshake), attaching a provider to an existing account from the callback
 * link flow, and the small role/name normalisation helpers.
 *
 * Extracted from SocialAuthController so the controller only wires HTTP
 * (validation, response envelopes, redirects, token/exchange plumbing).
 */
final readonly class SocialAuthService
{
    public function __construct(private UtmAttributionService $utm) {}

    /**
     * Resolve the social user from the provider using the OAuth token.
     * Apple sends an id_token; the others use the access token.
     */
    public function getSocialUser(string $provider, string $token, ?string $idToken = null): mixed
    {
        $driver = Socialite::driver($provider);

        // Facebook only returns the split first/last name (and a usable avatar)
        // when we request those fields explicitly; the default field set omits
        // `first_name`/`last_name`, so the prénom never gets captured. Keep
        // `picture` so the avatar still comes through.
        if ($provider === 'facebook' && $driver instanceof FacebookProvider) {
            $driver->fields(['first_name', 'last_name', 'name', 'email', 'picture.width(1920)']);
        }

        if ($provider === 'apple' && $idToken) {
            /** @phpstan-ignore method.notFound */
            return $driver->userFromToken($idToken);
        }

        /** @phpstan-ignore method.notFound */
        return $driver->userFromToken($token);
    }

    /**
     * Find existing user or create a new one from OAuth data.
     *
     * Resolution order: by provider id, then by email (which requires an
     * explicit link confirmation before attaching the provider), otherwise a
     * fresh account is created with the sanitized requested role.
     *
     * @param  array<string, mixed>  $utmPayload
     */
    public function findOrCreateUser(
        mixed $socialUser,
        string $provider,
        Request $request,
        array $utmPayload,
        string $requestedRole = 'customer',
    ): SocialAuthResult {
        $providerIdField = $provider.'_id';

        return DB::transaction(function () use ($socialUser, $provider, $providerIdField, $request, $utmPayload, $requestedRole): SocialAuthResult {
            // Try to find by provider ID first
            $user = User::where($providerIdField, $socialUser->getId())->first();

            if ($user) {
                // Refresh the profile captured from the provider on every login:
                // the displayed `avatar` column is re-synced and the first/last
                // name are healed only when still empty/placeholder (see
                // User::syncOAuthProfile()). `oauth_avatar` keeps tracking the raw
                // provider URL used by the pending-link handshake.
                $rawAvatar = $socialUser->getAvatar();
                $avatarUrl = is_string($rawAvatar) && trim($rawAvatar) !== '' ? $rawAvatar : null;

                if ($avatarUrl !== null && $user->oauth_avatar !== $avatarUrl) {
                    $user->oauth_avatar = $avatarUrl;
                }

                $names = $this->parseNames($socialUser);
                $user->syncOAuthProfile($names['firstname'], $names['lastname'], $avatarUrl);

                return new SocialAuthResult($user, false);
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

                return new SocialAuthResult($existingUser, false, true, $linkingToken);
            }

            // Create new user with the sanitized requested role (customer par
            // défaut, agent quand le flux vient du panel/app owners — jamais
            // admin). Un agent OAuth démarre en type `individual` ; le
            // rattachement à une agence passe par l'onboarding post-création.
            $names = $this->parseNames($socialUser);

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
            $user->forceFill($this->utm->attributesForNewUser($request, $utmPayload));
            $user->save();
            $this->utm->linkSessionVisitsToUser(
                $user,
                isset($utmPayload['session_id']) && is_string($utmPayload['session_id']) ? $utmPayload['session_id'] : null,
            );

            return new SocialAuthResult($user, true);
        });
    }

    /**
     * Resolve the pending link_code (issued by linkRedirect) and attach
     * $provider to that account. Returns the outcome so the caller can build
     * the redirect: 'linked' on success, or 'expired' | 'invalid' |
     * 'already_used'. Consumes the link_code.
     */
    public function completeLinkFromCode(string $provider, string $linkCode, mixed $socialUser): string
    {
        $userId = Cache::pull('oauth_link_'.$linkCode);

        if (!$userId) {
            return 'expired';
        }

        $user = User::find($userId);
        if (!$user || !$socialUser || !$socialUser->getId()) {
            return 'invalid';
        }

        $providerIdField = $provider.'_id';
        $alreadyLinked = User::where($providerIdField, $socialUser->getId())
            ->where('id', '!=', $user->id)
            ->exists();
        if ($alreadyLinked) {
            return 'already_used';
        }

        $user->forceFill([
            $providerIdField => $socialUser->getId(),
            'oauth_provider' => $user->oauth_provider ?? $provider,
        ])->save();

        return 'linked';
    }

    /**
     * Whitelist the role a client may request at OAuth account creation.
     *
     * Only `customer` (default) and `agent` (owner panel / owners app) are
     * accepted — `admin` or any other value silently falls back to customer.
     */
    public function sanitizeRequestedRole(mixed $role): string
    {
        return $role === 'agent' ? 'agent' : 'customer';
    }

    /**
     * Parse first and last names from social user data.
     *
     * Prefers the provider's structured given/family names (Google, Apple, and
     * Facebook once the first_name/last_name fields are requested), then falls
     * back to splitting the display name, then to the public username — GitHub
     * frequently leaves the display name blank, so `getNickname()` (the GitHub
     * login) keeps us from storing the bare "Utilisateur" placeholder.
     *
     * @return array{firstname: string, lastname: string}
     */
    private function parseNames(mixed $socialUser): array
    {
        $raw = method_exists($socialUser, 'getRaw') ? $socialUser->getRaw() : [];

        $firstname = $raw['given_name'] ?? $raw['first_name'] ?? null;
        $lastname = $raw['family_name'] ?? $raw['last_name'] ?? null;

        if (!is_string($firstname) || trim($firstname) === '') {
            $name = $socialUser->getName();

            if (!is_string($name) || trim($name) === '') {
                $name = method_exists($socialUser, 'getNickname') ? $socialUser->getNickname() : null;
            }

            $parts = explode(' ', is_string($name) ? trim($name) : '', 2);
            $firstname = $parts[0];

            if (!is_string($lastname) || trim($lastname) === '') {
                $lastname = $parts[1] ?? '';
            }
        }

        return [
            'firstname' => trim($firstname) !== '' ? trim($firstname) : 'Utilisateur',
            'lastname' => is_string($lastname) ? trim($lastname) : '',
        ];
    }
}
