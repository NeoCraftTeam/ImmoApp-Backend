<?php

declare(strict_types=1);

namespace App\Swagger;

/**
 * @OA\Schema(
 *     schema="AdImage",
 *     type="object",
 *     title="Ad Image",
 *     description="Image associée à une annonce",
 *
 *     @OA\Property(property="id", type="integer", description="Identifiant unique de l'image", example=1),
 *     @OA\Property(property="ad_id", type="integer", description="ID de l'annonce associée", example=1),
 *     @OA\Property(property="is_primary", type="boolean", description="Image principale", example=true),
 *     @OA\Property(property="created_at", type="string", format="date-time", description="Date de création", example="2024-01-15T10:30:00Z"),
 *     @OA\Property(property="updated_at", type="string", format="date-time", description="Date de modification", example="2024-01-15T10:30:00Z")
 * )
 */
final class AdImageSchema {}
