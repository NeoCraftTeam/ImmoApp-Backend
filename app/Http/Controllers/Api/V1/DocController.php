<?php

namespace App\Http\Controllers\Api\V1;

/**
 * @OA\Info(
 * title="KeyHome api",
 * version="v1",
 * description="Documentation complète de KeyHome api",
 *
 * @OA\License(
 * name="MIT",
 * url="https://opensource.org/licenses/MIT"
 * )
 * )
 *
 * @OA\SecurityScheme(
 * securityScheme="bearerAuth",
 * type="http",
 * scheme="bearer",
 * in="header",
 * name="Authorization",
 * bearerFormat="JWT"
 * )
 *
 * @OA\Tag(
 *     name="🏷️ Type d'annonce",
 *     description="Gestion des types d'annonces"
 * )
 * @OA\Tag(
 *     name="🏙️ Ville",
 *     description="Gestion des villes"
 * )
 * @OA\Tag(
 *      name="🔐 Authentification",
 *      description="Gestion de l'authentification"
 *  )
 * @OA\Tag(
 *       name="👤 Utilisateur",
 *       description="Gestion des utilisateurs"
 * )
 * @OA\Tag(
 *        name="📍 Quartier",
 *        description="Gestion des quartiers"
 * )
 * @OA\Tag(
 *         name="🏠 Annonces",
 *         description="Gestion des annonces"
 * )
 * @OA\Tag(
 *          name="🔍 Filtre",
 *          description="Filtrer les annonces"
 *  )
 */
class DocController
{
}
