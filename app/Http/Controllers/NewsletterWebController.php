<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\NewsletterSubscriber;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Handles newsletter unsubscribe links clicked from an email client.
 * Returns a human-readable HTML page instead of a JSON API response.
 */
final class NewsletterWebController
{
    public function __invoke(Request $request, string $token): Response
    {
        $subscriber = NewsletterSubscriber::where('token', $token)->first();

        if (!$subscriber) {
            return response()->view('newsletter.unsubscribe', [
                'success' => false,
                'email' => null,
            ], 404);
        }

        if ($subscriber->unsubscribed_at === null) {
            $subscriber->unsubscribed_at = now();
            $subscriber->save();
        }

        return response()->view('newsletter.unsubscribe', [
            'success' => true,
            'email' => $subscriber->email,
        ]);
    }
}
