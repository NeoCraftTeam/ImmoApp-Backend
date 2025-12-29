<?php

declare(strict_types=1);

namespace App\Swagger;

/**
 * @OA\Schema(
 *     schema="ErrorResponse",
 *     type="object",
 *     title="Error Response",
 *     description="Réponse d'erreur standard",
 *
 *     @OA\Property(property="success", type="boolean", example=false),
 *     @OA\Property(property="message", type="string", example="Une erreur s'est produite"),
 *     @OA\Property(property="error", type="string", example="Détail de l'erreur", nullable=true),
 *     @OA\Property(
 *         property="errors",
 *         type="object",
 *         description="Erreurs de validation",
 *         nullable=true,
 *         additionalProperties={"type": "array", "items": {"type": "string"}}
 *     )
 * )
 */
final class ErrorResponseSchema {}
