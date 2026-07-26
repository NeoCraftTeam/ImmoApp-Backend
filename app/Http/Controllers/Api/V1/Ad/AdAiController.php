<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Ad;

use App\Services\Ai\AiDescriptionEnhancer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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
    public function __invoke(Request $request): JsonResponse
    {
        $request->validate([
            'description' => ['required', 'string', 'max:10000'],
        ]);

        $enhanced = app(AiDescriptionEnhancer::class)->enhance($request->input('description'));

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
    public function generateFromAttributes(Request $request): JsonResponse
    {
        $data = $request->validate([
            'type' => ['nullable', 'string', 'max:100'],
            'city' => ['nullable', 'string', 'max:100'],
            'quarter' => ['nullable', 'string', 'max:100'],
            'bedrooms' => ['nullable', 'integer', 'min:0', 'max:50'],
            'surface' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'price' => ['nullable', 'numeric', 'min:0'],
            'transaction_type' => ['nullable', 'string', 'max:50'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

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
    public function enhanceTitle(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:500'],
            'type' => ['nullable', 'string', 'max:100'],
            'city' => ['nullable', 'string', 'max:100'],
            'transaction_type' => ['nullable', 'string', 'max:50'],
        ]);

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
    public function stream(Request $request): StreamedResponse
    {
        $request->validate([
            'description' => ['required', 'string', 'max:10000'],
        ]);

        $description = $request->input('description');

        return response()->stream(function () use ($description): void {
            app(AiDescriptionEnhancer::class)->streamEnhance(
                $description,
                function (string $chunk): void {
                    echo 'data: '.json_encode(['delta' => $chunk])."\n\n";
                    ob_flush();
                    flush();
                }
            );

            echo "event: done\ndata: {}\n\n";
            ob_flush();
            flush();
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'X-Accel-Buffering' => 'no',
        ]);
    }
}
