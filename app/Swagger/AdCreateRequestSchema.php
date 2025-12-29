<?php

declare(strict_types=1);

namespace App\Swagger;

/**
 * @OA\Schema(
 *     schema="AdCreateRequest",
 *     type="object",
 *     title="Ad Create Request",
 *     description="Données requises pour créer une annonce",
 *     required={"title", "description", "adresse", "price", "surface_area", "bedrooms", "bathrooms", "latitude", "longitude", "quarter_id", "type_id"},
 *
 *     @OA\Property(property="title", type="string", maxLength=255, description="Titre de l'annonce", example="Magnifique appartement"),
 *     @OA\Property(property="description", type="string", description="Description détaillée", example="Appartement spacieux avec vue"),
 *     @OA\Property(property="adresse", type="string", maxLength=500, description="Adresse du bien", example="123 Rue de la Paix"),
 *     @OA\Property(property="price", type="number", format="float", minimum=0, description="Prix", example=1250.50),
 *     @OA\Property(property="surface_area", type="number", format="float", minimum=0, description="Surface en m²", example=85.5),
 *     @OA\Property(property="bedrooms", type="integer", minimum=0, description="Nombre de chambres", example=2),
 *     @OA\Property(property="bathrooms", type="integer", minimum=0, description="Nombre de salles de bain", example=1),
 *     @OA\Property(property="has_parking", type="boolean", description="Parking disponible", example=true),
 *     @OA\Property(property="latitude", type="number", format="float", minimum=-90, maximum=90, description="Latitude GPS", example=48.8566),
 *     @OA\Property(property="longitude", type="number", format="float", minimum=-180, maximum=180, description="Longitude GPS", example=2.3522),
 *     @OA\Property(property="status", type="string", enum={"pending", "active", "expired", "sold"}, description="Statut", example="pending"),
 *     @OA\Property(property="expires_at", type="string", format="date-time", description="Date d'expiration", example="2024-12-31T23:59:59Z", nullable=true),
 *     @OA\Property(property="user_id", type="integer", description="ID utilisateur (optionnel)", example=1, nullable=true),
 *     @OA\Property(property="quarter_id", type="integer", description="ID quartier", example=5),
 *     @OA\Property(property="type_id", type="integer", description="ID type de bien", example=2),
 *     @OA\Property(
 *         property="images",
 *         type="array",
 *         description="Images du bien (max 10, 5MB chaque)",
 *
 *         @OA\Items(type="string", format="binary"),
 *         maxItems=10
 *     )
 * )
 */
final class AdCreateRequestSchema {}
