<?php

declare(strict_types=1);

namespace App\Swagger;

/**
 * @OA\Schema(
 *     schema="Ad",
 *     type="object",
 *     title="Ad",
 *     description="Modèle Annonce immobilière",
 *
 *     @OA\Property(property="id", type="integer", description="Identifiant unique de l'annonce", example=1),
 *     @OA\Property(property="title", type="string", maxLength=255, description="Titre de l'annonce", example="Magnifique appartement au centre-ville"),
 *     @OA\Property(property="description", type="string", description="Description détaillée de l'annonce", example="Superbe appartement de 3 pièces avec vue imprenable sur la ville"),
 *     @OA\Property(property="adresse", type="string", maxLength=500, description="Adresse du bien", example="123 Rue de la Paix, 75001 Paris"),
 *     @OA\Property(property="price", type="number", format="float", minimum=0, description="Prix du bien", example=1250.50),
 *     @OA\Property(property="surface_area", type="number", format="float", minimum=0, description="Surface en m²", example=85.5),
 *     @OA\Property(property="bedrooms", type="integer", minimum=0, description="Nombre de chambres", example=2),
 *     @OA\Property(property="bathrooms", type="integer", minimum=0, description="Nombre de salles de bain", example=1),
 *     @OA\Property(property="has_parking", type="boolean", description="Disponibilité d'un parking", example=true),
 *     @OA\Property(property="latitude", type="number", format="float", minimum=-90, maximum=90, description="Latitude GPS", example=48.8566),
 *     @OA\Property(property="longitude", type="number", format="float", minimum=-180, maximum=180, description="Longitude GPS", example=2.3522),
 *     @OA\Property(
 *         property="status",
 *         type="string",
 *         enum={"pending", "active", "expired", "sold"},
 *         description="Statut de l'annonce",
 *         example="active"
 *     ),
 *     @OA\Property(property="expires_at", type="string", format="date-time", description="Date d'expiration", example="2024-12-31T23:59:59Z", nullable=true),
 *     @OA\Property(property="created_at", type="string", format="date-time", description="Date de création", example="2024-01-15T10:30:00Z"),
 *     @OA\Property(property="updated_at", type="string", format="date-time", description="Date de dernière modification", example="2024-01-16T14:20:00Z"),
 *     @OA\Property(property="user_id", type="integer", description="ID du propriétaire de l'annonce", example=1),
 *     @OA\Property(property="quarter_id", type="integer", description="ID du quartier", example=5),
 *     @OA\Property(property="type_id", type="integer", description="ID du type de bien", example=2)
 * )
 */
final class AdSchema {}
