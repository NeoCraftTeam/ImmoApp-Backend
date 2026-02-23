<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Mail\InvitationMail;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Handles inbound Clerk webhook events (delivered via Svix).
 *
 * Supported events:
 *   - invitation.created             → InvitationMail
 *   - organizationInvitation.created → InvitationMail
 */
final class ClerkWebhookController
{
    public function handle(Request $request): JsonResponse
    {
        $rawBody = $request->getContent();

        if (!$this->isSignatureValid($request, $rawBody)) {
            Log::warning('Clerk webhook: invalid signature', [
                'ip' => $request->ip(),
            ]);

            return response()->json(['message' => 'Invalid signature.'], 401);
        }

        /** @var array<string,mixed> $payload */
        $payload = json_decode($rawBody, true) ?? [];
        $type = (string) ($payload['type'] ?? '');

        /** @var array<string,mixed> $data */
        $data = $payload['data'] ?? [];

        return match (true) {
            in_array($type, ['invitation.created', 'organizationInvitation.created'], true) => $this->handleInvitationCreated($data),
            default => response()->json(['message' => 'Event acknowledged.']),
        };
    }

    /** @param array<string,mixed> $data */
    private function handleInvitationCreated(array $data): JsonResponse
    {
        $emailAddress = (string) ($data['email_address'] ?? '');

        if ($emailAddress === '') {
            return response()->json(['message' => 'Missing email.'], 422);
        }

        $actionUrl = (string) ($data['url'] ?? '');

        // expires_at may be a Unix timestamp in seconds or milliseconds
        $expiresAt = (int) ($data['expires_at'] ?? 0);
        if ($expiresAt > 1_000_000_000_000) {
            $expiresAt = (int) ($expiresAt / 1000);
        }
        $expiresInDays = $expiresAt > 0
            ? max(1, (int) ceil(($expiresAt - now()->timestamp) / 86400))
            : 7;

        $inviterName = isset($data['inviter_user_id'])
            ? $this->resolveInviterName((string) $data['inviter_user_id'])
            : null;

        Mail::to($emailAddress)
            ->queue(new InvitationMail(
                actionUrl: $actionUrl,
                expiresInDays: $expiresInDays,
                inviterName: $inviterName,
            ));

        return response()->json(['message' => 'OK']);
    }

    /**
     * Verify the Svix webhook signature.
     *
     * @see https://clerk.com/docs/integrations/webhooks/overview#protect-your-webhooks-from-abuse
     */
    private function isSignatureValid(Request $request, string $rawBody): bool
    {
        $secret = (string) config('clerk.webhook_secret', '');

        if ($secret === '') {
            Log::error('Clerk webhook: CLERK_WEBHOOK_SECRET is not configured.');

            return false;
        }

        $svixId = (string) $request->header('svix-id', '');
        $svixTimestamp = (string) $request->header('svix-timestamp', '');
        $svixSignature = (string) $request->header('svix-signature', '');

        if ($svixId === '' || $svixTimestamp === '' || $svixSignature === '') {
            return false;
        }

        // Reject replays older than 5 minutes
        if (abs(now()->timestamp - (int) $svixTimestamp) > 300) {
            return false;
        }

        // Decode the secret (strip the "whsec_" prefix then base64-decode)
        $secretBytes = base64_decode(str_replace('whsec_', '', $secret), true);
        if ($secretBytes === false) {
            return false;
        }

        $signedContent = $svixId.'.'.$svixTimestamp.'.'.$rawBody;
        $computed = base64_encode(hash_hmac('sha256', $signedContent, $secretBytes, true));

        // svix-signature may contain multiple space-separated "v1,<sig>" entries
        foreach (explode(' ', $svixSignature) as $part) {
            [$version, $sig] = array_pad(explode(',', $part, 2), 2, '');
            if ($version === 'v1' && hash_equals($computed, $sig)) {
                return true;
            }
        }

        return false;
    }

    private function resolveInviterName(string $clerkUserId): ?string
    {
        $user = User::query()->where('clerk_id', $clerkUserId)->first();

        return $user !== null ? trim($user->firstname.' '.$user->lastname) : null;
    }
}
