<?php

namespace App\Swagger;

/**
 * @OA\Schema(
 *     schema="Quarter",
 *     type="object",
 *     title="Quarter",
 *     description="Modèle quartier",
 *
 *     @OA\Property(property="id", type="integer"),
 *      @OA\Property(property="name", type="string"),
 *      @OA\Property(property="city_id", type="int"),
 * )
 */
class QuarterSchema {}
