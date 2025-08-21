<?php

namespace App\Swagger;

/**
 * @OA\Schema(
 *     schema="User",
 *     type="object",
 *     title="User",
 *     description="Modèle utilisateur",
 *     @OA\Property(property="id", type="integer"),
 *      @OA\Property(property="firstname", type="string"),
 *      @OA\Property(property="lastname", type="string"),
 *      @OA\Property(property="phone_number", type="string"),
 *      @OA\Property(property="email", type="string"),
 *      @OA\Property(property="password", type="string"),
 *      @OA\Property(property="confirm_password", type="string"),
 *      @OA\Property(property="avatar", type="string"),
 *     @OA\Property(
 *         property="role",
 *         type="string",
 *         enum={"admin","agent","customer"}
 *     ),
 *     @OA\Property(
 *         property="type",
 *         type="string",
 *         enum={"individual","agency"},
 *         nullable=true
 *     ),
 *     @OA\Property(property="city_id", type="integer")
 * )
 */
class UserSchema
{
}
