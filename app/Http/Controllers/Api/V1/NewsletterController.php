<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\NewsletterSubscribeRequest;
use App\Mail\NewsletterConfirmationMail;
use App\Models\NewsletterSubscriber;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class NewsletterController
{
    public function subscribe(NewsletterSubscribeRequest $request): JsonResponse
    {
        $subscriber = NewsletterSubscriber::firstOrCreate(
            ['email' => strtolower((string) $request->validated('email'))],
            [
                'name' => $request->validated('name'),
                'locale' => $request->validated('locale', 'fr'),
                'source' => $request->validated('source', 'website'),
                'confirmed_at' => now(),
            ]
        );

        $isResubscribing = $subscriber->unsubscribed_at !== null;
        $isNew = $subscriber->wasRecentlyCreated;

        if ($isResubscribing) {
            $subscriber->unsubscribed_at = null;
            $subscriber->confirmed_at = now();
            $subscriber->save();
        }

        if ($isNew || $isResubscribing) {
            try {
                Mail::to($subscriber->email)
                    ->send(new NewsletterConfirmationMail($subscriber));
            } catch (\Throwable $e) {
                Log::error('Newsletter confirmation email failed', [
                    'email' => $subscriber->email,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return response()->json([
            'message' => 'Inscription réussie. Merci de vous être abonné à notre newsletter.',
        ], 201);
    }

    public function unsubscribe(Request $request, string $token): JsonResponse
    {
        $subscriber = NewsletterSubscriber::where('token', $token)->first();

        if (!$subscriber) {
            return response()->json(['message' => 'Lien invalide ou expiré.'], 404);
        }

        $subscriber->unsubscribed_at = now();
        $subscriber->save();

        return response()->json(['message' => 'Désinscription effectuée avec succès.']);
    }
}
