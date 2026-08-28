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
        $data = $request->validate($this->enhanceRules());

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
        $data = $request->validate($this->enhanceRules());

        $description = $data['description'];
        $context = $this->contextFrom($data);

        return response()->stream(function () use ($description, $context): void {
            app(AiDescriptionEnhancer::class)->streamEnhance(
                $description,
                function (string $chunk): void {
                    echo 'data: '.json_encode(['delta' => $chunk])."\n\n";
                    ob_flush();
                    flush();
                },
                $context,
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

    /**
     * Validation rules shared by the JSON and SSE enhance endpoints: the raw
     * description plus optional form facts the model can weave in without inventing.
     * `attributes` stays lenient (array only) so the mobile client's `string[]`
     * of feature labels is accepted as-is.
     *
     * @return array<string, array<int, string>>
     */
    private function enhanceRules(): array
    {
        return [
            'description' => ['required', 'string', 'max:10000'],
            'type' => ['nullable', 'string', 'max:100'],
            'city' => ['nullable', 'string', 'max:100'],
            'quarter' => ['nullable', 'string', 'max:100'],
            'bedrooms' => ['nullable', 'integer', 'min:0', 'max:50'],
            'surface' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'price' => ['nullable', 'numeric', 'min:0'],
            'transaction_type' => ['nullable', 'string', 'max:50'],
            'attributes' => ['nullable', 'array'],
        ];
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
