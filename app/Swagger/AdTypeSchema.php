<?php

declare(strict_types=1);

namespace App\Swagger;

/**
 * @OA\Schema(
 *     schema="AdType",
 *     type="object",
 *     title="AdType",
 *     description="Modèle pour type d'annonce",
 *
 *     @OA\Property(property="id", type="integer"),
 *      @OA\Property(property="name", type="string"),
 *      @OA\Property(property="desc", type="string"),
 * )
 */
final class AdTypeSchema {}
