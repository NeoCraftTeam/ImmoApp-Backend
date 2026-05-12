<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\SupportContactRequest;
use App\Mail\SupportContactMail;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Public support endpoints (contact form, etc.).
 *
 * Routes are unauthenticated and rate-limited at the route layer
 * (see routes/api.php).
 */
final readonly class SupportController
{
    /**
     * Receive a public contact form submission and forward it to the support inbox.
     *
     * @OA\Post(
     *     path="/api/v1/support/contact",
     *     tags={"📩 Support"},
     *     summary="Soumettre le formulaire de contact public",
     *
     *     @OA\Response(response=202, description="Message reçu et en cours d'envoi"),
     *     @OA\Response(response=422, description="Données invalides"),
     *     @OA\Response(response=429, description="Trop de tentatives")
     * )
     */
    public function contact(SupportContactRequest $request): JsonResponse
    {
        $payload = $request->validated();

        $recipient = (string) (config('mail.support_address')
            ?? config('mail.from.address')
            ?? 'support@keyhome.app');

        $contactName = trim((string) $payload['name']);
        $contactEmail = mb_strtolower(trim((string) $payload['email']));
        $contactSubject = trim((string) $payload['subject']);
        $contactMessage = trim((string) $payload['message']);

        try {
            Mail::to($recipient)->queue(new SupportContactMail(
                contactName: $contactName,
                contactEmail: $contactEmail,
                contactSubject: $contactSubject,
                contactMessage: $contactMessage,
                sourceIp: $request->ip(),
                userAgent: $request->userAgent(),
                sourceUrl: $request->header('Referer'),
            ));
        } catch (Throwable $e) {
            Log::error('Support contact mail dispatch failed', [
                'email' => $contactEmail,
                'subject' => $contactSubject,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => "Une erreur est survenue lors de l'envoi. Veuillez réessayer dans un instant.",
            ], 500);
        }

        Log::info('Support contact form submitted', [
            'email' => $contactEmail,
            'subject' => $contactSubject,
            'ip' => $request->ip(),
        ]);

        return response()->json([
            'message' => 'Votre message a bien été envoyé. Notre équipe vous répondra rapidement.',
        ], 202);
    }
}
