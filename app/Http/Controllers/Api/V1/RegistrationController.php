<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\RegisterRequest;
use App\Services\RegistrationService;
use Illuminate\Http\JsonResponse;

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
     *     path="/api/v1/auth/registerAdmin",
     *     summary="Enregistrer un administrateur",
     *     tags={"🔐 Authentification"},
     *     security={{"sanctum":{}}},
     *
     *     @OA\Response(response=201, description="Admin créé"),
     *     @OA\Response(response=401, description="Non authentifié"),
     *     @OA\Response(response=403, description="Accès interdit")
     * )
     */
    public function registerAdmin(RegisterRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['role'] = 'admin';

        return $this->registrationService->register($data, $request);
    }
}
