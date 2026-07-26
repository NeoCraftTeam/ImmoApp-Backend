<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\DTOs\LoginResult;
use App\Exceptions\AccountInactiveException;
use App\Exceptions\EmailNotVerifiedException;
use App\Exceptions\RoleContextMismatchException;
use App\Http\Requests\LoginRequest;
use App\Mail\NewDeviceSignInMail;
use App\Mail\NewLocationSignInMail;
use App\Models\LoginHistory;
use App\Models\User;
use App\Services\TurnstileService;
use App\Services\User\UserAgentParser;
use App\Support\AuthError;
use App\Support\FrontendRedirectGuard;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Laravel\Sanctum\NewAccessToken;

final readonly class LoginService
{
    public function __construct(
        private TokenService $tokenService,
        private TurnstileService $turnstile,
    ) {}

    /**
     * Authenticate a user from a login request.
     *
     * @throws ThrottleRequestsException
     * @throws AuthenticationException
     * @throws AccountInactiveException
     * @throws EmailNotVerifiedException
     * @throws RoleContextMismatchException
     */
    public function authenticate(LoginRequest $request): LoginResult
    {
        $credentials = $request->validated();
        $email = mb_strtolower(trim((string) $credentials['email']));
        $password = $credentials['password'];

        $key = 'login-attempts:'.$request->ip().'|'.mb_strtolower((string) $email);

        $this->checkRateLimit($key, $request, $email);

        // Cloudflare Turnstile — vérifié UNIQUEMENT pour les clients
        // stateful (web SPA avec session Sanctum). Les apps mobiles
        // natives envoient `X-KeyHome-Client` et n'ont pas de widget.
        if (
            $request->hasSession()
            && !FrontendRedirectGuard::isMobileAppRequest($request)
            && $this->turnstile->isConfigured()
            && !$this->turnstile->verify(
                $request->input('turnstile_token'),
                $request->ip(),
            )
        ) {
            RateLimiter::hit($key, 300);
            Log::info('Login rejected: Turnstile verification failed', [
                'ip' => $request->ip(),
                'email' => $email,
            ]);
            throw new AuthenticationException(AuthError::LOGIN_FAILURE_MESSAGE);
        }

        $user = User::where('email', $email)->first();

        // Anti-énumération temporelle : quand l'email n'existe pas, un
        // court-circuit booléen sautait le Hash::check (~100 ms de bcrypt)
        // et la réponse revenait nettement plus vite que pour un compte
        // existant — le message générique ne suffisait donc pas. On calcule
        // toujours une comparaison bcrypt pour égaliser le temps de réponse.
        if ($user === null) {
            // Compare contre un hash bidon uniquement pour dépenser le même
            // temps CPU ; le résultat est volontairement ignoré (le compte
            // n'existe pas, l'authentification échoue).
            Hash::check($password, self::dummyPasswordHash());
            $passwordMatches = false;
        } else {
            $passwordMatches = Hash::check($password, (string) $user->password);
        }

        if (!$user || !$passwordMatches) {
            RateLimiter::hit($key, 300);

            Log::warning('Failed login attempt', [
                'email' => $email,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'timestamp' => now(),
            ]);

            throw new AuthenticationException(AuthError::LOGIN_FAILURE_MESSAGE);
        }

        if (isset($user->is_active) && !$user->is_active) {
            Log::info('Login attempt on inactive account', [
                'user_id' => $user->id,
                'email' => $email,
            ]);

            throw new AccountInactiveException;
        }

        if ($user->email_verified_at === null) {
            // L'OTP appartient au flux d'INSCRIPTION, pas à la connexion :
            // on ne renvoie jamais de nouveau code ici. Le client route vers
            // l'écran de vérification (seule voie pour activer le compte) où
            // l'utilisateur saisit le code d'inscription ou touche « Renvoyer »
            // (à la demande, protégé par cooldown). Se connecter ne doit pas
            // déclencher l'envoi d'un code.
            throw new EmailNotVerifiedException(
                email: $user->email,
                role: $user->role->value,
            );
        }

        $loginContext = $request->input('login_context', 'client');
        $this->enforceRoleContext($user, $loginContext);

        RateLimiter::clear($key);

        $token = $this->rotateApiTokenForLoginContext($user, $loginContext);

        $this->detectNewLocation($user, $request);
        $this->recordLogin($user, $request);

        Log::info('Successful login', [
            'user_id' => $user->id,
            'email' => $email,
            'is_spa' => $request->hasSession(),
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return new LoginResult(user: $user, token: $token);
    }

    /**
     * Enforce owner vs client login panel rules, then rotate a Sanctum API token
     * (same naming and abilities as password login).
     *
     * Used by WebAuthn API login after the assertion is verified.
     *
     * @throws RoleContextMismatchException
     */
    public function issueApiTokenForLoginContext(User $user, string $loginContext): NewAccessToken
    {
        $this->assertRoleContext($user, $loginContext);

        return $this->rotateApiTokenForLoginContext($user, $loginContext);
    }

    /**
     * @throws RoleContextMismatchException
     */
    public function assertRoleContext(User $user, string $loginContext): void
    {
        $this->enforceRoleContext($user, $loginContext);
    }

    private function rotateApiTokenForLoginContext(User $user, string $loginContext): NewAccessToken
    {
        $prefix = $loginContext === 'owner' ? 'owner' : 'client';

        return $this->tokenService->rotateForUser($user, 'token', "{$prefix}_token_%", $prefix);
    }

    /**
     * Hash bcrypt factice, mis en cache statique, comparé quand l'email
     * n'existe pas — jamais un hash d'utilisateur réel.
     */
    private static function dummyPasswordHash(): string
    {
        static $hash = null;

        return $hash ??= Hash::make('keyhome-timing-equalizer');
    }

    private function checkRateLimit(string $key, LoginRequest $request, string $email): void
    {
        if (!RateLimiter::tooManyAttempts($key, 5)) {
            return;
        }

        $seconds = RateLimiter::availableIn($key);

        Log::warning('Too many login attempts', [
            'ip' => $request->ip(),
            'email' => $email,
            'user_agent' => $request->userAgent(),
        ]);

        throw new ThrottleRequestsException(
            'Trop de tentatives de connexion. Réessayez dans '.$seconds.' secondes.',
            headers: ['Retry-After' => (string) $seconds],
        );
    }

    private function enforceRoleContext(User $user, string $loginContext): void
    {
        if ($loginContext === 'owner' && !$user->mayAccessOwnerPanel()) {
            throw new RoleContextMismatchException(AuthError::CODE_PANEL_ACCESS_DENIED);
        }

        if ($loginContext === 'client' && !$user->isCustomer() && !$user->isAdmin()) {
            throw new RoleContextMismatchException(AuthError::CODE_PANEL_ACCESS_DENIED);
        }
    }

    private function recordLogin(User $user, LoginRequest $request): void
    {
        $user->forceFill([
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
            'last_login_country' => strtoupper(trim($request->header('CF-IPCountry', ''))) ?: null,
            'last_login_city' => mb_convert_case(trim($request->header('CF-IPCity', '')), MB_CASE_TITLE) ?: null,
        ])->save();

        $parsed = UserAgentParser::parse($request->userAgent() ?? '');

        LoginHistory::create([
            'user_id' => $user->id,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'device_type' => $parsed['device_type'],
            'browser' => $parsed['browser_name'],
            'platform' => $parsed['operating_system'],
            'country' => strtoupper(trim($request->header('CF-IPCountry', ''))) ?: null,
            'city' => mb_convert_case(trim($request->header('CF-IPCity', '')), MB_CASE_TITLE) ?: null,
            'guard' => 'sanctum',
            'successful' => true,
        ]);
    }

    private function detectNewLocation(User $user, LoginRequest $request): void
    {
        $currentIp = $request->ip();
        $cfCountry = $request->header('CF-IPCountry', '');
        $cfCity = $request->header('CF-IPCity', '');

        $currentCountry = strtoupper(trim($cfCountry));
        $currentCity = mb_convert_case(trim($cfCity), MB_CASE_TITLE);

        $knownCountry = $user->last_login_country ?? '';
        $knownCity = $user->last_login_city ?? '';
        $knownIp = $user->last_login_ip ?? '';

        $locationChanged = $knownCountry !== '' && (
            $currentCountry !== $knownCountry ||
            ($currentCity !== '' && $currentCity !== $knownCity)
        );

        $ua = UserAgentParser::parse($request->userAgent() ?? '');

        $tz = config('app.timezone', 'Africa/Douala');
        $carbonNow = now()->setTimezone($tz);
        $offset = $carbonNow->format('P'); // +01:00
        $loginAt = $carbonNow->translatedFormat('d F Y \\à H:i')
            .' (UTC'.str_replace(':00', '', $offset).')';

        $locationLabel = match (true) {
            $currentCity !== '' && $currentCountry !== '' => $currentCity.', '.$currentCountry,
            $currentCity !== '' => $currentCity,
            $currentCountry !== '' => $currentCountry,
            default => $currentIp,
        };

        if ($locationChanged) {
            Mail::to($user->email, $user->firstname)->queue(new NewLocationSignInMail(
                userName: $user->firstname ?? $user->email,
                city: $currentCity ?: 'Inconnue',
                country: $currentCountry ?: 'Inconnu',
                ipAddress: $currentIp,
                device: $ua['device_type'],
                browser: $ua['browser_name'],
                operatingSystem: $ua['operating_system'],
                loginAt: $loginAt,
                secureAccountUrl: config('app.frontend_url').'/security/sessions',
                supportEmail: config('mail.from.address'),
            ));
        } elseif ($currentIp !== $knownIp && $knownIp !== '') {
            Mail::to($user->email, $user->firstname)->queue(new NewDeviceSignInMail(
                deviceType: $ua['device_type'],
                browserName: $ua['browser_name'],
                operatingSystem: $ua['operating_system'],
                location: $locationLabel,
                ipAddress: $currentIp,
                sessionCreatedAt: $loginAt,
                userName: $user->firstname ?? $user->email,
                signInMethod: 'Email / Mot de passe',
                revokeSessionUrl: config('app.frontend_url').'/security/sessions',
                supportEmail: config('mail.from.address'),
            ));
        }
    }
}
