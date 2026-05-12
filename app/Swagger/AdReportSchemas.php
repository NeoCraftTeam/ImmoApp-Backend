<?php

declare(strict_types=1);

namespace App\Swagger;

/**
 * @OA\Schema(
 *     schema="AdReportStoreRequest",
 *     type="object",
 *     required={"reason"},
 *     description="Payload de signalement d'annonce. Les champs `scam_reason`, `payment_methods` et `description` sont conditionnels.",
 *
 *     @OA\Property(
 *         property="reason",
 *         type="string",
 *         enum={"inaccurate","not_real_property","scam","shocking_content","other"},
 *         example="scam",
 *         description="Motif principal du signalement."
 *     ),
 *     @OA\Property(
 *         property="scam_reason",
 *         type="string",
 *         nullable=true,
 *         enum={"asked_off_platform_payment","shared_contacts","promoting_external_services","duplicate_listing","misleading_listing"},
 *         example="asked_off_platform_payment",
 *         description="Obligatoire uniquement si `reason=scam`."
 *     ),
 *     @OA\Property(
 *         property="payment_methods",
 *         type="array",
 *         nullable=true,
 *         description="Obligatoire uniquement si `reason=scam` et `scam_reason=asked_off_platform_payment`.",
 *         minItems=1,
 *         maxItems=7,
 *
 *         @OA\Items(type="string", enum={"bank_transfer","card","cash","paypal","moneygram","western_union","other"})
 *     ),
 *
 *     @OA\Property(
 *         property="description",
 *         type="string",
 *         nullable=true,
 *         minLength=10,
 *         maxLength=2000,
 *         example="Le propriétaire m'a demandé de payer en dehors de la plateforme.",
 *         description="Obligatoire uniquement si `reason=other`."
 *     )
 * )
 *
 * @OA\Schema(
 *     schema="AdReportStoreSuccessResponse",
 *     type="object",
 *     required={"message","data"},
 *
 *     @OA\Property(property="message", type="string", example="Signalement envoye. Merci de nous aider a proteger la communaute."),
 *     @OA\Property(
 *         property="data",
 *         type="object",
 *         required={"id","status"},
 *         @OA\Property(property="id", type="string", format="uuid"),
 *         @OA\Property(property="status", type="string", enum={"pending","reviewing","resolved","dismissed"}, example="pending")
 *     )
 * )
 *
 * @OA\Schema(
 *     schema="AdReportValidationErrorResponse",
 *     type="object",
 *     required={"message","errors"},
 *
 *     @OA\Property(property="message", type="string", example="The given data was invalid."),
 *     @OA\Property(
 *         property="errors",
 *         type="object",
 *         additionalProperties=@OA\Schema(type="array", @OA\Items(type="string"))
 *     )
 * )
 */
final class AdReportSchemas {}
