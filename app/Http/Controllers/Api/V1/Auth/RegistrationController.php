<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\DTOs\RegistrationResult;
use App\Exceptions\RegistrationEmailTakenException;
use App\Http\Requests\Api\V1\Auth\CheckEmailAvailabilityRequest;
use App\Http\Requests\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\Auth\RegistrationService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Spatie\MediaLibrary\MediaCollections\Exceptions\FileDoesNotExist;
use Spatie\MediaLibrary\MediaCollections\Exceptions\FileIsTooBig;
use Throwable;

final readonly class RegistrationController
{
    public function __construct(private RegistrationService $registrationService) {}

    /**
     * @OA\Post(
     *     path="/api/v1/auth/registerCustomer",
     *     tags={"🔐 Authentification"},
     *     summary="Inscription d'un nouveau client",
     *     operationId="registerCustomer",
     *
     *     @OA\Response(response=201, description="Inscription réussie"),
     *     @OA\Response(response=409, description="Email déjà utilisé"),
     *     @OA\Response(response=422, description="Erreur de validation"),
     *     @OA\Response(response=429, description="Trop de tentatives")
     * )
     */
    public function registerCustomer(RegisterRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['role'] = 'customer';
        $data['type'] = 'individual';

        return $this->handleRegistration($data, $request);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/auth/registerAgent",
     *     tags={"🔐 Authentification"},
     *     summary="Inscription d'un nouvel agent",
     *     operationId="registerAgent",
     *
     *     @OA\Response(response=201, description="Inscription réussie"),
     *     @OA\Response(response=409, description="Email déjà utilisé"),
     *     @OA\Response(response=422, description="Erreur de validation"),
     *     @OA\Response(response=429, description="Trop de tentatives")
     * )
     */
    public function registerAgent(RegisterRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['role'] = 'agent';

        return $this->handleRegistration($data, $request);
    }

    /**
     * Bootstrap a new administrator account. Reachable ONLY by an existing
     * authenticated admin (route guarded by `auth:sanctum` + `mfa.admin` +
     * `can:admin-access`). The controller itself stays role-agnostic — the
     * three-middleware stack is the authority; we just forward to the
     * shared registration handler with `role = admin`.
     *
     * @OA\Post(
     *     path="/api/v1/auth/registerAdmin",
     *     tags={"🔐 Authentification"},
     *     summary="Inscription d'un nouvel administrateur (admin uniquement)",
     *     operationId="registerAdmin",
     *     security={{"sanctum":{}}},
     *
     *     @OA\Response(response=201, description="Admin créé"),
     *     @OA\Response(response=401, description="Non authentifié"),
     *     @OA\Response(response=403, description="Accès admin requis (ou MFA manquant)"),
     *     @OA\Response(response=409, description="Email déjà utilisé"),
     *     @OA\Response(response=422, description="Erreur de validation"),
     *     @OA\Response(response=429, description="Trop de tentatives")
     * )
     */
    public function registerAdmin(RegisterRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['role'] = 'admin';
        $data['type'] = 'individual';

        Log::info('admin.registration.initiated', [
            'created_by_user_id' => $request->user()?->id,
            'new_admin_email' => $data['email'] ?? null,
            'ip' => $request->ip(),
        ]);

        return $this->handleRegistration($data, $request);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/auth/check-email",
     *     tags={"🔐 Authentification"},
     *     summary="Vérifier la disponibilité d'une adresse email",
     *     operationId="checkEmail",
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(@OA\Property(property="email", type="string", format="email"))
     *     ),
     *
     *     @OA\Response(response=200, description="Résultat de disponibilité"),
     *     @OA\Response(response=422, description="Email invalide"),
     *     @OA\Response(response=429, description="Trop de tentatives")
     * )
     */
    public function checkEmail(CheckEmailAvailabilityRequest $request): JsonResponse
    {
        $request->validated();

        $available = !User::query()
            ->where('email', $request->string('email')->lower())
            ->exists();

        // SEC-W15: constant-time response regardless of availability — prevents
        // an attacker from building a valid-email list via response-time analysis.
        usleep(200_000); // 200 ms

        return response()->json(['available' => $available]);
    }

    /**
     * Shared registration handler — maps service result/exceptions to HTTP responses.
     *
     * @param  array<string, mixed>  $data
     */
    private function handleRegistration(array $data, RegisterRequest $request): JsonResponse
    {
        try {
            $result = $this->registrationService->register($data, $request);

            return $this->successResponse($result);

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
            Log::warning('Registration duplicate email (DB constraint)', [
                'email' => $data['email'] ?? 'unknown',
                'ip' => $request->ip(),
            ]);

            return response()->json([
                'message' => 'Cette adresse email est déjà utilisée.',
            ], 409);

        } catch (RegistrationEmailTakenException $e) {
            throw $e;
        } catch (Throwable $e) {
            Log::error('Registration failed', [
                'exception' => $e->getMessage(),
                'request_data' => $request->except(['password', 'avatar']),
            ]);
            throw $e;
        }
    }

    private function successResponse(RegistrationResult $result): JsonResponse
    {
        return response()->json([
            'message' => 'Inscription réussie.',
            'user' => new UserResource($result->user),
            'access_token' => $result->token->plainTextToken,
            'email_verification_required' => $result->emailVerificationRequired,
        ], 201);
    }
}
