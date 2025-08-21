<?php

namespace App\Http\Controllers\Api\V1;

/**
 * @OA\Info(
 * title="KeyHome api",
 * version="v1",
 * description="Documentation complète de KeyHome api",
 * @OA\License(
 * name="MIT",
 * url="https://opensource.org/licenses/MIT"
 * )
 * )
 *
 *
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
 *
 *
 * @OA\Tag(
 *     name="ad type",
 *     description="Gestion des types d'annonces"
 * )
 *
 * @OA\Tag(
 *     name="city",
 *     description="Gestion des villes"
 * )
 *
 * @OA\Tag(
 *      name="auth",
 *      description="Gestion de l'authentification"
 *  )
 */
class DocController
{

}
