<?php

declare(strict_types=1);

namespace App\Services;

use App\Http\Resources\UserResource;
use App\Models\User;
use App\Support\GeoLocation;
use Illuminate\Auth\Events\Registered;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Psr\Log\LoggerInterface;
use Spatie\MediaLibrary\MediaCollections\Exceptions\FileDoesNotExist;
use Spatie\MediaLibrary\MediaCollections\Exceptions\FileIsTooBig;
use Throwable;

final readonly class RegistrationService
{
    public function __construct(
        private LoggerInterface $log,
        private UtmAttributionService $utmAttribution,
    ) {}

    /**
     * Register a new user with the given data and request context.
     *
     * @param  array<string, mixed>  $data
     */
    public function register(array $data, FormRequest $request): JsonResponse
    {
        try {
            $key = 'register-attempts:'.$request->ip();
            if (RateLimiter::tooManyAttempts($key, 10)) {
                $seconds = RateLimiter::availableIn($key);

                $this->log->warning('Too many registration attempts', [
                    'ip' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                ]);

                return response()->json([
                    'message' => 'Trop de tentatives d\'inscription. Réessayez dans '.$seconds.' secondes.',
                    'retry_after' => $seconds,
                ], 429);
            }

            $data = array_merge($request->validated(), $data);

            if (User::where('email', $data['email'])->exists()) {
                RateLimiter::hit($key, 600);

                $this->log->warning('Registration attempt with existing email', [
                    'email' => $data['email'],
                    'ip' => $request->ip(),
                ]);

                return response()->json([
                    'message' => 'Les informations fournies sont invalides.',
                ], 422);
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

                    return response()->json([
                        'message' => 'Vous ne pouvez pas créer plus de comptes depuis cette adresse IP.',
                    ], 422);
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

                $token = $user->createToken(
                    'registration_token_'.now()->timestamp,
                    ['*'],
                    now()->addDay()
                );

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
            auth()->setUser($user);

            if ($request->hasSession()) {
                $request->session()->regenerate();
                Auth::guard('web')->login($user);
            }

            return response()->json([
                'message' => 'Inscription réussie.',
                'user' => new UserResource($user),
                'access_token' => $token->plainTextToken,
                'email_verification_required' => $user->email_verified_at === null,
            ], 201);

        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Erreur de validation.',
                'errors' => $e->errors(),
            ], 422);

        } catch (FileIsTooBig) {
            return response()->json([
                'message' => 'Le fichier avatar est trop volumineux.',
                'max_size' => '2MB',
            ], 413);

        } catch (FileDoesNotExist) {
            return response()->json([
                'message' => 'Le fichier avatar est introuvable.',
            ], 400);

        } catch (UniqueConstraintViolationException) {
            $this->log->warning('Registration duplicate email (DB constraint)', [
                'email' => $data['email'] ?? 'unknown',
                'ip' => $request->ip(),
            ]);

            return response()->json([
                'message' => 'Cette adresse email est déjà utilisée.',
            ], 409);

        } catch (Throwable $e) {
            $this->log->error('Registration failed', [
                'error' => config('app.debug') ? $e->getMessage() : 'An internal error occurred.',
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->except(['password', 'avatar']),
            ]);

            return response()->json([
                'message' => 'Une erreur est survenue lors de l\'inscription. Veuillez réessayer.',
                'error' => config('app.debug') ? $e->getMessage() : 'An internal error occurred.',
            ], 500);
        }
    }
}
