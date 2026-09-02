<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Ad;

use App\Http\Requests\Api\V1\Ad\EnhanceAdDescriptionRequest;
use App\Http\Requests\Api\V1\Ad\EnhanceAdTitleRequest;
use App\Http\Requests\Api\V1\Ad\GenerateAdDescriptionRequest;
use App\Services\Ai\AiDescriptionEnhancer;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class AdAiController
{
    /**
     * @OA\Post(
     *     path="/api/v1/ads/ai/enhance-description",
     *     summary="Améliorer une description d'annonce via IA",
     *     description="Utilise l'IA pour améliorer la description d'une annonce immobilière.",
     *     tags={"🏠 Annonces"},
     *     security={{"sanctum":{}}},
     *
     *     @OA\RequestBody(required=true,
     *
     *         @OA\JsonContent(
     *             required={"description"},
     *
     *             @OA\Property(property="description", type="string", maxLength=10000)
     *         )
     *     ),
     *
     *     @OA\Response(response=200, description="Description améliorée"),
     *     @OA\Response(response=401, description="Non authentifié"),
     *     @OA\Response(response=422, description="Validation échouée")
     * )
     */
    public function __invoke(EnhanceAdDescriptionRequest $request): JsonResponse
    {
        $data = $request->validated();

        $enhanced = app(AiDescriptionEnhancer::class)->enhance(
            $data['description'],
            $this->contextFrom($data),
        );

        return response()->json(['enhanced' => $enhanced]);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/ads/ai/generate-from-attributes",
     *     summary="Générer une description à partir des attributs du bien",
     *     description="Génère une description immobilière professionnelle à partir des caractéristiques du bien (type, ville, surface, etc.).",
     *     tags={"🏠 Annonces"},
     *     security={{"sanctum":{}}},
     *
     *     @OA\RequestBody(required=true,
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="type", type="string"),
     *             @OA\Property(property="city", type="string"),
     *             @OA\Property(property="quarter", type="string"),
     *             @OA\Property(property="bedrooms", type="integer"),
     *             @OA\Property(property="surface", type="number"),
     *             @OA\Property(property="price", type="number"),
     *             @OA\Property(property="transaction_type", type="string"),
     *             @OA\Property(property="notes", type="string", maxLength=2000)
     *         )
     *     ),
     *
     *     @OA\Response(response=200, description="Description générée"),
     *     @OA\Response(response=401, description="Non authentifié"),
     *     @OA\Response(response=422, description="Validation échouée")
     * )
     */
    public function generateFromAttributes(GenerateAdDescriptionRequest $request): JsonResponse
    {
        $data = $request->validated();

        $generated = app(AiDescriptionEnhancer::class)->generateFromAttributes(
            array_filter($data, fn ($v) => $v !== null)
        );

        return response()->json(['generated' => $generated]);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/ads/ai/enhance-title",
     *     summary="Améliorer le titre d'une annonce via IA",
     *     description="Génère un titre concis, accrocheur et factuel pour une annonce immobilière.",
     *     tags={"🏠 Annonces"},
     *     security={{"sanctum":{}}},
     *
     *     @OA\RequestBody(required=true,
     *
     *         @OA\JsonContent(
     *             required={"title"},
     *
     *             @OA\Property(property="title", type="string", maxLength=500),
     *             @OA\Property(property="type", type="string"),
     *             @OA\Property(property="city", type="string"),
     *             @OA\Property(property="transaction_type", type="string")
     *         )
     *     ),
     *
     *     @OA\Response(response=200, description="Titre amélioré"),
     *     @OA\Response(response=401, description="Non authentifié"),
     *     @OA\Response(response=422, description="Validation échouée")
     * )
     */
    public function enhanceTitle(EnhanceAdTitleRequest $request): JsonResponse
    {
        $data = $request->validated();

        $context = array_filter([
            'type' => $data['type'] ?? null,
            'city' => $data['city'] ?? null,
            'transaction_type' => $data['transaction_type'] ?? null,
        ]);

        $enhanced = app(AiDescriptionEnhancer::class)->enhanceTitle($data['title'], $context);

        return response()->json(['enhanced' => $enhanced]);
    }

    /**
     * Stream the AI-enhanced description as Server-Sent Events.
     * Each SSE event carries a `delta` key with the next token chunk.
     * A final `event: done` signals end-of-stream.
     */
    public function stream(EnhanceAdDescriptionRequest $request): StreamedResponse
    {
        $data = $request->validated();

        $description = $data['description'];
        $context = $this->contextFrom($data);

        return response()->stream(function () use ($description, $context): void {
            $emit = static function (string $payload): void {
                echo $payload;
                // `output_buffering` may be Off (common in prod). Flushing a
                // buffer that isn't there raises a PHP warning that Laravel
                // escalates to an ErrorException — which, once headers are sent,
                // leaks the generic 500 JSON into the open SSE stream.
                if (ob_get_level() > 0) {
                    ob_flush();
                }
                flush();
            };

            try {
                app(AiDescriptionEnhancer::class)->streamEnhance(
                    $description,
                    static function (string $chunk) use ($emit): void {
                        $emit('data: '.json_encode(['delta' => $chunk])."\n\n");
                    },
                    $context,
                );
            } catch (\Throwable $e) {
                Log::error('AI stream-enhance failed mid-stream: '.$e->getMessage());
                $emit('event: error'."\n".'data: '.json_encode([
                    'message' => "L'amélioration IA est momentanément indisponible. Réessayez dans un instant.",
                ])."\n\n");
            }

            $emit("event: done\ndata: {}\n\n");
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    /**
     * Turn validated request data into the context array consumed by the enhancer:
     * flat facts (dropping nulls) plus the feature labels under `features`.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function contextFrom(array $data): array
    {
        $context = array_filter([
            'type' => $data['type'] ?? null,
            'city' => $data['city'] ?? null,
            'quarter' => $data['quarter'] ?? null,
            'bedrooms' => $data['bedrooms'] ?? null,
            'surface' => $data['surface'] ?? null,
            'price' => $data['price'] ?? null,
            'transaction_type' => $data['transaction_type'] ?? null,
        ], static fn ($value): bool => $value !== null);

        $attributes = $data['attributes'] ?? null;
        if (is_array($attributes) && $attributes !== []) {
            $context['features'] = $attributes;
        }

        return $context;
    }
}
