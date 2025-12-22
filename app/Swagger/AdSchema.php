<?php

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
class AdSchema {}

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
class AdResourceSchema {}

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
class AdImageSchema {}

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
class ErrorResponseSchema {}

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
class AdCreateRequestSchema {}

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
class AdUpdateRequestSchema {}
