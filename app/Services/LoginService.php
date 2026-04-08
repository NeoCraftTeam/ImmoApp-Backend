<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\LoginResult;
use App\Exceptions\AccountInactiveException;
use App\Exceptions\EmailNotVerifiedException;
use App\Exceptions\RoleContextMismatchException;
use App\Http\Requests\LoginRequest;
use App\Mail\NewDeviceSignInMail;
use App\Mail\NewLocationSignInMail;
use App\Models\LoginHistory;
use App\Models\User;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;

final readonly class LoginService
{
    public function __construct(private TokenService $tokenService) {}

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
        $email = $credentials['email'];
        $password = $credentials['password'];

        $key = 'login-attempts:'.$request->ip().'|'.mb_strtolower((string) $email);

        $this->checkRateLimit($key, $request, $email);

        $user = User::where('email', $email)->first();

        if (!$user || !Hash::check($password, $user->password)) {
            RateLimiter::hit($key, 300);

            Log::warning('Failed login attempt', [
                'email' => $email,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'timestamp' => now(),
            ]);

            throw new AuthenticationException('Identifiants invalides.');
        }

        if (isset($user->is_active) && !$user->is_active) {
            Log::info('Login attempt on inactive account', [
                'user_id' => $user->id,
                'email' => $email,
            ]);

            throw new AccountInactiveException;
        }

        if ($user->email_verified_at === null) {
            // Re-trigger OTP so the user can verify immediately.
            $user->sendEmailVerificationNotification();
            throw new EmailNotVerifiedException(
                email: $user->email,
                role: $user->role->value,
            );
        }

        $loginContext = $request->input('login_context', 'client');
        $this->enforceRoleContext($user, $loginContext);

        RateLimiter::clear($key);

        $prefix = $loginContext === 'owner' ? 'owner' : 'client';
        $token = $this->tokenService->rotateForUser($user, 'token', "{$prefix}_token_%", $prefix);

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
        if ($loginContext === 'owner' && !$user->isAgent() && !$user->isAdmin()) {
            throw new RoleContextMismatchException('Accès réservé aux propriétaires et agences.');
        }

        if ($loginContext === 'client' && !$user->isCustomer() && !$user->isAdmin()) {
            throw new RoleContextMismatchException('Accès réservé aux clients. Utilisez le panneau propriétaire.');
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

        if ($locationChanged) {
            Mail::to($user->email, $user->firstname)->queue(new NewLocationSignInMail(
                userName: $user->firstname ?? $user->email,
                city: $currentCity ?: 'Inconnue',
                country: $currentCountry ?: 'Inconnu',
                ipAddress: $currentIp,
                device: $ua['device_type'],
                browser: $ua['browser_name'],
                operatingSystem: $ua['operating_system'],
                loginAt: now()->translatedFormat('d F Y \\à H:i'),
                secureAccountUrl: config('app.frontend_url').'/security/sessions',
                supportEmail: config('mail.from.address'),
            ));
        } elseif ($currentIp !== $knownIp && $knownIp !== '') {
            Mail::to($user->email, $user->firstname)->queue(new NewDeviceSignInMail(
                deviceType: $ua['device_type'],
                browserName: $ua['browser_name'],
                operatingSystem: $ua['operating_system'],
                location: ($currentCity ?: $currentIp).', '.$currentCountry,
                ipAddress: $currentIp,
                sessionCreatedAt: now()->translatedFormat('d F Y \\à H:i'),
                signInMethod: 'Email / Mot de passe',
                revokeSessionUrl: config('app.frontend_url').'/security/sessions',
                supportEmail: config('mail.from.address'),
            ));
        }
    }
}
