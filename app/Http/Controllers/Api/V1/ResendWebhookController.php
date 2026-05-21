<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Models\EmailSuppression;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * E-2 : Resend webhook handler — bounce / complaint tracking.
 *
 * Signature verification uses the Svix HMAC scheme that Resend adopts:
 *   signed_content = svix-id + "." + svix-timestamp + "." + raw_body
 *   computed = base64( HMAC-SHA256(signed_content, secret_bytes) )
 *   secret_bytes = base64_decode( substr(RESEND_WEBHOOK_SECRET, 7) )  // strip "whsec_"
 *
 * If the signature is invalid the endpoint returns 401 immediately.
 *
 * Handled event types:
 *  - email.bounced         → permanent hard bounce → suppress
 *  - email.complained      → spam complaint        → suppress
 *  - email.delivery_delayed → soft bounce          → log only
 *
 * All other event types are acknowledged (200) without side effects.
 */
final class ResendWebhookController
{
    public function handle(Request $request): JsonResponse
    {
        $secret = config('services.resend.webhook_secret');

        if ($secret !== null && $secret !== '') {
            if (!$this->isSignatureValid($request, (string) $secret)) {
                Log::warning('resend.webhook.invalid_signature', [
                    'ip' => $request->ip(),
                ]);

                return response()->json(['message' => 'Invalid signature'], 401);
            }
        }

        /** @var array<string, mixed> $payload */
        $payload = $request->json()->all();

        $eventType = (string) ($payload['type'] ?? '');
        $data = (array) ($payload['data'] ?? []);

        match ($eventType) {
            'email.bounced' => $this->handleBounce($data, $eventType),
            'email.complained' => $this->handleComplaint($data, $eventType),
            'email.delivery_delayed' => $this->logSoftBounce($data),
            default => null,
        };

        return response()->json(['received' => true]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function handleBounce(array $data, string $eventType): void
    {
        $email = strtolower(trim((string) ($data['to'][0] ?? $data['email'] ?? '')));

        if ($email === '') {
            return;
        }

        EmailSuppression::updateOrCreate(
            ['email' => $email],
            [
                'reason' => 'bounce',
                'resend_event_type' => $eventType,
                'metadata' => $data,
            ],
        );

        Log::info('resend.webhook.bounce_suppressed', ['email' => $email]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function handleComplaint(array $data, string $eventType): void
    {
        $email = strtolower(trim((string) ($data['to'][0] ?? $data['email'] ?? '')));

        if ($email === '') {
            return;
        }

        EmailSuppression::updateOrCreate(
            ['email' => $email],
            [
                'reason' => 'complaint',
                'resend_event_type' => $eventType,
                'metadata' => $data,
            ],
        );

        Log::info('resend.webhook.complaint_suppressed', ['email' => $email]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function logSoftBounce(array $data): void
    {
        $email = strtolower(trim((string) ($data['to'][0] ?? $data['email'] ?? '')));

        Log::warning('resend.webhook.delivery_delayed', ['email' => $email]);
    }

    private function isSignatureValid(Request $request, string $secret): bool
    {
        $svixId = $request->header('svix-id', '');
        $svixTimestamp = $request->header('svix-timestamp', '');
        $svixSignature = $request->header('svix-signature', '');

        if ($svixId === '' || $svixTimestamp === '' || $svixSignature === '') {
            return false;
        }

        $rawBody = $request->getContent();
        $signedContent = "{$svixId}.{$svixTimestamp}.{$rawBody}";

        $secretBytes = base64_decode(substr($secret, 7));
        $computedSignature = 'v1,'.base64_encode(
            hash_hmac('sha256', $signedContent, $secretBytes, true)
        );

        return array_any(explode(' ', (string) $svixSignature), fn ($sig) => hash_equals($computedSignature, trim((string) $sig)));
    }
}
