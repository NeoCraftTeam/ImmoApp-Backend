<?php

declare(strict_types=1);

namespace App\Swagger;

/**
 * @OA\Schema(
 *     schema="AdResource",
 *     type="object",
 *     title="Ad Resource",
 *     description="Ressource complète d'une annonce avec relations",
 *     allOf={
 *         @OA\Schema(ref="#/components/schemas/Ad"),
 *         @OA\Schema(
 *
 *             @OA\Property(
 *                 property="user",
 *                 type="object",
 *                 description="Propriétaire de l'annonce",
 *                 @OA\Property(property="id", type="integer", example=1),
 *                 @OA\Property(property="firstname", type="string", example="John"),
 *                 @OA\Property(property="lastname", type="string", example="Doe"),
 *                 @OA\Property(property="email", type="string", example="john.doe@example.com"),
 *                 @OA\Property(property="phone_number", type="string", example="+33123456789"),
 *                 @OA\Property(property="avatar", type="string", nullable=true, example="https://example.com/avatar.jpg")
 *             ),
 *             @OA\Property(
 *                 property="quarter",
 *                 type="object",
 *                 description="Quartier du bien",
 *                 @OA\Property(property="id", type="integer", example=5),
 *                 @OA\Property(property="name", type="string", example="Centre-ville"),
 *                 @OA\Property(
 *                     property="city",
 *                     type="object",
 *                     description="Ville du quartier",
 *                     @OA\Property(property="id", type="integer", example=1),
 *                     @OA\Property(property="name", type="string", example="Paris"),
 *                     @OA\Property(property="postal_code", type="string", example="75001")
 *                 )
 *             ),
 *             @OA\Property(
 *                 property="ad_type",
 *                 type="object",
 *                 description="Type de bien",
 *                 @OA\Property(property="id", type="integer", example=2),
 *                 @OA\Property(property="name", type="string", example="Appartement"),
 *                 @OA\Property(property="description", type="string", example="Logement dans un immeuble")
 *             ),
 *             @OA\Property(
 *                 property="images",
 *                 type="array",
 *                 description="Images de l'annonce",
 *
 *                 @OA\Items(
 *                     type="object",
 *
 *                     @OA\Property(property="id", type="integer", example=1),
 *                     @OA\Property(property="is_primary", type="boolean", example=true),
 *                     @OA\Property(
 *                         property="media",
 *                         type="array",
 *
 *                         @OA\Items(
 *                             type="object",
 *
 *                             @OA\Property(property="id", type="integer", example=1),
 *                             @OA\Property(property="name", type="string", example="Image principale"),
 *                             @OA\Property(property="file_name", type="string", example="1_1642234567_0.jpg"),
 *                             @OA\Property(property="mime_type", type="string", example="image/jpeg"),
 *                             @OA\Property(property="size", type="integer", example=1024576),
 *                             @OA\Property(property="url", type="string", example="https://example.com/storage/images/1_1642234567_0.jpg"),
 *                             @OA\Property(property="created_at", type="string", format="date-time", example="2024-01-15T10:30:00Z")
 *                         )
 *                     )
 *                 )
 *             ),
 *             @OA\Property(property="images_count", type="integer", description="Nombre total d'images", example=4)
 *         )
 *     }
 * )
 */
final class AdResourceSchema {}
