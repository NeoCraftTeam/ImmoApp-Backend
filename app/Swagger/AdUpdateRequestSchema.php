<?php

declare(strict_types=1);

namespace App\Swagger;

/**
 * @OA\Schema(
 *     schema="AdUpdateRequest",
 *     type="object",
 *     title="Ad Update Request",
 *     description="Données pour mettre à jour une annonce",
 *
 *     @OA\Property(property="title", type="string", maxLength=255, description="Titre de l'annonce", example="Titre mis à jour"),
 *     @OA\Property(property="description", type="string", description="Description mise à jour", example="Description modifiée"),
 *     @OA\Property(property="adresse", type="string", maxLength=500, description="Nouvelle adresse", example="456 Avenue Updated"),
 *     @OA\Property(property="price", type="number", format="float", minimum=0, description="Nouveau prix", example=1350.75),
 *     @OA\Property(property="surface_area", type="number", format="float", minimum=0, description="Nouvelle surface", example=90.0),
 *     @OA\Property(property="bedrooms", type="integer", minimum=0, description="Nouveau nombre de chambres", example=3),
 *     @OA\Property(property="bathrooms", type="integer", minimum=0, description="Nouveau nombre de salles de bain", example=2),
 *     @OA\Property(property="has_parking", type="boolean", description="Parking disponible", example=false),
 *     @OA\Property(property="latitude", type="number", format="float", minimum=-90, maximum=90, description="Nouvelle latitude", example=48.8606),
 *     @OA\Property(property="longitude", type="number", format="float", minimum=-180, maximum=180, description="Nouvelle longitude", example=2.3376),
 *     @OA\Property(property="status", type="string", enum={"pending", "active", "expired", "sold"}, description="Nouveau statut", example="active"),
 *     @OA\Property(property="expires_at", type="string", format="date-time", description="Nouvelle date d'expiration", example="2025-01-31T23:59:59Z"),
 *     @OA\Property(property="quarter_id", type="integer", description="Nouveau quartier", example=3),
 *     @OA\Property(property="type_id", type="integer", description="Nouveau type", example=1),
 *     @OA\Property(
 *         property="images",
 *         type="array",
 *         description="Nouvelles images à ajouter",
 *
 *         @OA\Items(type="string", format="binary")
 *     ),
 *
 *     @OA\Property(
 *         property="images_to_delete",
 *         type="array",
 *         description="IDs des images à supprimer",
 *
 *         @OA\Items(type="integer"),
 *         example={1, 3, 5}
 *     )
 * )
 */
final class AdUpdateRequestSchema {}
