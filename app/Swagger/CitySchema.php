<?php

declare(strict_types=1);

namespace App\Swagger;

/**
 * @OA\Schema(
 *     schema="City",
 *     type="object",
 *     title="City",
 *     description="Modèle pour une ville",
 *
 *     @OA\Property(property="id", type="integer"),
 *      @OA\Property(property="name", type="string"),
 * )
 */
final class CitySchema {}
