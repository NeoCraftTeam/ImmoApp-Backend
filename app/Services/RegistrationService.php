<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\RegistrationResult;
use App\Exceptions\RegistrationEmailTakenException;
use App\Models\User;
use App\Support\GeoLocation;
use Illuminate\Auth\Events\Registered;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Psr\Log\LoggerInterface;

final readonly class RegistrationService
{
    public function __construct(
        private LoggerInterface $log,
        private UtmAttributionService $utmAttribution,
        private TokenService $tokenService,
    ) {}

    /**
     * Register a new user with the given data and request context.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws ThrottleRequestsException
     * @throws ValidationException
     * @throws RegistrationEmailTakenException
     */
    public function register(array $data, FormRequest $request): RegistrationResult
    {
        $key = 'register-attempts:'.$request->ip();
        if (RateLimiter::tooManyAttempts($key, 10)) {
            $seconds = RateLimiter::availableIn($key);

            $this->log->warning('Too many registration attempts', [
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            throw new ThrottleRequestsException(
                'Trop de tentatives d\'inscription. Réessayez dans '.$seconds.' secondes.',
                headers: ['Retry-After' => (string) $seconds],
            );
        }

        $data = array_merge($request->validated(), $data);

        $existingUser = User::query()->where('email', $data['email'])->first();
        if ($existingUser !== null) {
            RateLimiter::hit($key, 600);

            $this->log->warning('Registration attempt with existing email', [
                'email' => $data['email'],
                'ip' => $request->ip(),
            ]);

            throw RegistrationEmailTakenException::forExistingUser($existingUser);
        }

        $registrationIp = $request->ip();
        $isLocalhost = in_array($registrationIp, ['127.0.0.1', '::1'], true);
        if (!$isLocalhost && app()->isProduction()) {
            $existingAccountsFromIp = User::where('registration_ip', $registrationIp)
                ->where('created_at', '>=', now()->subDays((int) config('auth.ip_block_days', 30)))
                ->count();

            if ($existingAccountsFromIp >= (int) config('auth.max_accounts_per_ip', 5)) {
                $this->log->warning('Multi-account registration attempt blocked by IP', [
                    'ip' => $registrationIp,
                    'existing_accounts' => $existingAccountsFromIp,
                ]);

                throw ValidationException::withMessages([
                    'email' => ['Vous ne pouvez pas créer plus de comptes depuis cette adresse IP.'],
                ]);
            }
        }

        $result = DB::transaction(function () use ($request, $data) {
            $user = new User;
            $user->fill([
                'firstname' => $data['firstname'],
                'lastname' => $data['lastname'],
                'email' => $data['email'],
                'phone_number' => $data['phone_number'],
                'password' => $data['password'],
                'location' => GeoLocation::fromArray($data)?->toPoint(),
                'type' => $data['type'] ?? 'individual',
                'city_id' => $data['city_id'] ?? null,
            ]);
            // SEC-001: Only allow 'admin' role when an authenticated admin is creating the user.
            // Public registrations are restricted to 'customer' or 'agent'.
            $allowedRoles = ['customer', 'agent'];
            $role = in_array($data['role'] ?? null, $allowedRoles, true)
                ? $data['role']
                : 'customer';

            $user->forceFill([
                'role' => $role,
                'is_active' => true,
                'email_verified_at' => null,
                'last_login_ip' => $request->ip(),
                'registration_ip' => $request->ip(),
            ]);
            $user->forceFill($this->utmAttribution->attributesForNewUser($request, $data));
            $user->save();

            $this->utmAttribution->linkSessionVisitsToUser(
                $user,
                isset($data['session_id']) && is_string($data['session_id']) ? $data['session_id'] : null,
            );

            if ($request->hasFile('avatar')) {
                $user->clearMediaCollection('avatars');
                $user->addMediaFromRequest('avatar')
                    ->usingName($user->firstname.'_'.$user->lastname.'_avatar')
                    ->toMediaCollection('avatars');
            }

            $token = $this->tokenService->createForUser($user, 'registration');

            return ['user' => $user, 'token' => $token];
        });

        $user = $result['user'];
        $token = $result['token'];

        event(new Registered($user));

        RateLimiter::clear($key);

        $this->log->info('User registered successfully', [
            'user_id' => $user->id,
            'email' => $user->email,
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        $user->refresh();

        // Only login user to web session if email is already verified.
        // This prevents the frontend AuthProvider from detecting the user
        // as authenticated and redirecting to dashboard before OTP verification.
        if ($user->email_verified_at !== null) {
            auth()->setUser($user);

            if ($request->hasSession()) {
                $request->session()->regenerate();
                Auth::guard('web')->login($user);
            }
        }

        return new RegistrationResult(
            user: $user,
            token: $token,
            emailVerificationRequired: $user->email_verified_at === null,
        );
    }
}
