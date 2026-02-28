<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\InvoiceResource;
use App\Models\Agency;
use App\Models\Invoice;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use OpenApi\Annotations as OA;

/**
 * @OA\Tag(name="🧾 Factures", description="Consultation et téléchargement des factures d'abonnement")
 */
final class InvoiceController
{
    /**
     * @OA\Get(
     *     path="/api/v1/invoices",
     *     operationId="listInvoices",
     *     summary="Lister les factures de mon agence",
     *     description="Retourne la liste paginée des factures générées pour l'agence de l'utilisateur authentifié, triées de la plus récente à la plus ancienne. Chaque facture inclut un lien de téléchargement PDF.",
     *     tags={"🧾 Factures"},
     *     security={{"sanctum":{}}},
     *
     *     @OA\Response(
     *         response=200,
     *         description="Liste paginée des factures",
     *
     *         @OA\JsonContent(
     *             type="object",
     *
     *             @OA\Property(property="data", type="array", @OA\Items(
     *                 @OA\Property(property="id", type="string", format="uuid"),
     *                 @OA\Property(property="invoice_number", type="string", example="KH-202602-0001"),
     *                 @OA\Property(property="plan_name", type="string", example="Premium"),
     *                 @OA\Property(property="amount_formatted", type="string", example="35 000 XOF"),
     *                 @OA\Property(property="status", type="string", example="paid"),
     *                 @OA\Property(property="issued_at_formatted", type="string", example="01/02/2026"),
     *                 @OA\Property(property="download_url", type="string", example="https://api.keyhome.cm/api/v1/invoices/uuid/download")
     *             ))
     *         )
     *     ),
     *
     *     @OA\Response(response=401, description="Non authentifié"),
     *     @OA\Response(response=403, description="L'utilisateur n'appartient à aucune agence")
     * )
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        /** @var \App\Models\User $user */
        $user = $request->user();
        $agency = $user->agency;

        if (!$agency) {
            abort(403, 'Vous n\'appartenez à aucune agence.');
        }

        assert($agency instanceof Agency);

        $invoices = Invoice::query()
            ->where('agency_id', $agency->id)
            ->with('agency')
            ->orderByDesc('issued_at')
            ->paginate(15);

        return InvoiceResource::collection($invoices);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/invoices/{invoice}",
     *     operationId="showInvoice",
     *     summary="Détails d'une facture",
     *     description="Retourne les détails complets d'une facture identifiée par son UUID. L'utilisateur doit appartenir à l'agence concernée.",
     *     tags={"🧾 Factures"},
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(name="invoice", in="path", required=true, @OA\Schema(type="string", format="uuid")),
     *
     *     @OA\Response(response=200, description="Détails de la facture"),
     *     @OA\Response(response=403, description="Accès refusé"),
     *     @OA\Response(response=404, description="Facture introuvable")
     * )
     */
    public function show(Request $request, Invoice $invoice): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();
        /** @var \App\Models\Agency|null $agency */
        $agency = $user->agency;

        if (!$agency || $invoice->agency_id !== $agency->id) {
            return response()->json(['message' => 'Accès refusé.'], 403);
        }

        $invoice->load(['agency', 'subscription.plan']);

        return response()->json(['data' => new InvoiceResource($invoice)]);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/invoices/{invoice}/download",
     *     operationId="downloadInvoice",
     *     summary="Télécharger une facture en PDF",
     *     description="Génère et retourne la facture au format PDF, prête à être téléchargée ou affichée dans l'application.",
     *     tags={"🧾 Factures"},
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(name="invoice", in="path", required=true, @OA\Schema(type="string", format="uuid")),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Fichier PDF de la facture",
     *
     *         @OA\MediaType(mediaType="application/pdf")
     *     ),
     *
     *     @OA\Response(response=403, description="Accès refusé"),
     *     @OA\Response(response=404, description="Facture introuvable")
     * )
     */
    public function download(Request $request, Invoice $invoice): Response
    {
        /** @var \App\Models\User $user */
        $user = $request->user();
        /** @var \App\Models\Agency|null $agency */
        $agency = $user->agency;

        if (!$agency || $invoice->agency_id !== $agency->id) {
            abort(403, 'Accès refusé.');
        }

        $invoice->load(['agency', 'subscription.plan', 'payment']);

        $logoPath = public_path('images/keyhomelogo_transparent.png');
        $logoBase64 = file_exists($logoPath)
            ? 'data:image/png;base64,'.base64_encode((string) file_get_contents($logoPath))
            : null;

        $pdf = Pdf::loadView('invoices.pdf', [
            'invoice' => $invoice,
            'user' => $user,
            'logoBase64' => $logoBase64,
        ])
            ->setPaper('a4', 'portrait')
            ->setOptions([
                'isHtml5ParserEnabled' => true,
                'isRemoteEnabled' => false,
                'defaultFont' => 'DejaVu Sans',
            ]);

        $filename = 'facture-'.$invoice->invoice_number.'.pdf';

        return $pdf->download($filename);
    }
}
