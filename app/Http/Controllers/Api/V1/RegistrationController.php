<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\RegisterRequest;
use App\Models\User;
use App\Services\RegistrationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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

        return $this->registrationService->register($data, $request);
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

        return $this->registrationService->register($data, $request);
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
    public function checkEmail(Request $request): JsonResponse
    {
        $request->validate(['email' => ['required', 'email', 'max:255']]);

        $available = !User::query()
            ->where('email', $request->string('email')->lower())
            ->exists();

        return response()->json(['available' => $available]);
    }
}
