<?php

declare(strict_types=1);

namespace App\Swagger;

/**
 * @OA\Schema(
 *     schema="ErrorResponse",
 *     type="object",
 *     title="Error Response",
 *     description="Enveloppe d'erreur canonique. `message` est toujours présent et sûr (le contenu sensible est remplacé par un repli générique). `success` n'apparaît que sur les réponses issues de App\Support\ApiResponse.",
 *     required={"message"},
 *
 *     @OA\Property(property="message", type="string", example="Certaines informations sont invalides. Vérifiez le formulaire."),
 *     @OA\Property(property="code", type="string", example="SLOT_NOT_AVAILABLE", nullable=true, description="Code machine stable identifiant l'erreur (ex. NOT_FOUND, RATE_LIMITED, SLOT_NOT_AVAILABLE)."),
 *     @OA\Property(property="hint", type="string", example="Ce créneau n'existe pas ou la date est passée.", nullable=true, description="Indication complémentaire, uniquement si non sensible."),
 *     @OA\Property(property="retry_after", type="integer", example=30, nullable=true, description="Secondes avant nouvelle tentative (réponses 429)."),
 *     @OA\Property(property="success", type="boolean", example=false, nullable=true),
 *     @OA\Property(
 *         property="errors",
 *         type="object",
 *         description="Erreurs de validation par champ.",
 *         nullable=true,
 *         additionalProperties={"type": "array", "items": {"type": "string"}}
 *     )
 * )
 */
final class ErrorResponseSchema {}
