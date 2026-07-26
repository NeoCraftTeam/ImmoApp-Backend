<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\DisputeEvidenceType;
use App\Enums\DisputeStatus;
use App\Enums\DisputeType;
use App\Enums\UserRole;
use App\Models\Ad;
use App\Models\Dispute;
use App\Models\DisputeEvidence;
use App\Models\DisputeMessage;
use App\Models\LeaseContract;
use App\Models\Payment;
use App\Models\User;
use App\Notifications\DisputeMessageReceivedNotification;
use App\Notifications\DisputeOpenedNotification;
use App\Notifications\DisputeStatusChangedNotification;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

final class DisputeService
{
    /**
     * Default SLA window for admin to take charge of a dispute (in days).
     * Aligned with ODR benchmarks (3–7 business days).
     */
    private const int SLA_DAYS = 7;

    /**
     * @param  array{
     *     type: DisputeType|string,
     *     respondent_id?: string|null,
     *     title: string,
     *     description: string,
     *     amount_claimed?: int|null,
     *     ad_id?: string|null,
     *     lease_id?: string|null,
     *     payment_id?: string|null,
     * }  $payload
     */
    public function open(User $initiator, array $payload): Dispute
    {
        $respondentId = $payload['respondent_id'] ?? null;

        if ($respondentId === null) {
            $respondentId = $this->resolveRespondentFromContext($initiator, $payload);
        }

        if ($respondentId === null) {
            throw ValidationException::withMessages([
                'respondent_id' => "Impossible d'identifier la partie adverse à partir du contexte fourni.",
            ]);
        }

        if ($initiator->id === $respondentId) {
            throw ValidationException::withMessages([
                'respondent_id' => 'Vous ne pouvez pas ouvrir un litige contre vous-même.',
            ]);
        }

        $type = $payload['type'] instanceof DisputeType
            ? $payload['type']
            : DisputeType::from($payload['type']);

        /** @var Dispute $dispute */
        $dispute = DB::transaction(fn (): Dispute => Dispute::query()->create([
            'reference' => $this->generateReference(),
            'type' => $type,
            'status' => DisputeStatus::OPEN,
            'initiator_id' => $initiator->id,
            'respondent_id' => $respondentId,
            'ad_id' => $payload['ad_id'] ?? null,
            'lease_id' => $payload['lease_id'] ?? null,
            'payment_id' => $payload['payment_id'] ?? null,
            'title' => $payload['title'],
            'description' => $payload['description'],
            'amount_claimed' => $payload['amount_claimed'] ?? null,
            'sla_deadline' => now()->addDays(self::SLA_DAYS),
        ]));

        $dispute->load(['initiator', 'respondent', 'ad']);

        $this->notifyParties(
            $dispute,
            new DisputeOpenedNotification($dispute),
            excludeUserId: $initiator->id,
        );
        $this->notifyAdmins($dispute, new DisputeOpenedNotification($dispute));

        return $dispute;
    }

    public function addMessage(Dispute $dispute, User $sender, string $body, bool $isInternal = false): DisputeMessage
    {
        if (!$dispute->status->isOpen()) {
            throw ValidationException::withMessages([
                'body' => 'Ce litige est clos. Aucun nouveau message n\'est accepté.',
            ]);
        }

        if ($isInternal && ($sender->role ?? null) !== UserRole::ADMIN) {
            $isInternal = false; // only admins may post internal-only notes
        }

        /** @var DisputeMessage $message */
        $message = DB::transaction(fn (): DisputeMessage => DisputeMessage::query()->create([
            'dispute_id' => $dispute->id,
            'sender_id' => $sender->id,
            'body' => $body,
            'is_internal' => $isInternal,
        ]));

        if (!$isInternal) {
            $this->notifyParties(
                $dispute,
                new DisputeMessageReceivedNotification($dispute, $message),
                excludeUserId: $sender->id,
            );
        }

        return $message;
    }

    public function uploadEvidence(
        Dispute $dispute,
        User $uploader,
        UploadedFile $file,
        DisputeEvidenceType $type,
    ): DisputeEvidence {
        if (!$dispute->status->isOpen()) {
            throw ValidationException::withMessages([
                'file' => 'Ce litige est clos. Aucune nouvelle preuve n\'est acceptée.',
            ]);
        }

        $disk = config('filesystems.default', 'public');
        $directory = "disputes/{$dispute->id}";
        $path = $file->store($directory, $disk);

        if ($path === false || $path === '') {
            throw ValidationException::withMessages([
                'file' => 'L\'enregistrement du fichier a échoué. Réessayez.',
            ]);
        }

        return DisputeEvidence::query()->create([
            'dispute_id' => $dispute->id,
            'uploader_id' => $uploader->id,
            'type' => $type,
            'disk' => $disk,
            'path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(),
            'size_bytes' => $file->getSize() ?: null,
        ]);
    }

    public function transition(
        Dispute $dispute,
        User $admin,
        DisputeStatus $target,
        ?string $resolutionNote = null,
    ): Dispute {
        if (!$dispute->status->canTransitionTo($target)) {
            throw ValidationException::withMessages([
                'status' => "Transition interdite : {$dispute->status->value} → {$target->value}.",
            ]);
        }

        return DB::transaction(function () use ($dispute, $admin, $target, $resolutionNote): Dispute {
            $previous = $dispute->status;

            $dispute->status = $target;
            $dispute->admin_id = $admin->id;

            if ($target->isResolved()) {
                $dispute->resolved_at = now();
                $dispute->resolution_note = $resolutionNote;
            }

            $dispute->save();
            $dispute->refresh();

            $this->notifyParties(
                $dispute,
                new DisputeStatusChangedNotification($dispute, $previous, $target),
            );

            return $dispute;
        });
    }

    /**
     * Resolve the counterparty user id from the dispute context.
     *
     * - ad_id      → the ad owner (`ad.user_id`)
     * - lease_id   → the other party (tenant ↔ landlord)
     * - payment_id → the payment receiver (`payment.user_id`)
     *
     * Returns null when no context is provided or none can be resolved.
     *
     * @param  array<string, mixed>  $payload
     */
    private function resolveRespondentFromContext(User $initiator, array $payload): ?string
    {
        // 1) Ad owner — most common KeyHome case (renter ↔ landlord on a listing).
        if (!empty($payload['ad_id'])) {
            $ad = Ad::query()->find($payload['ad_id']);
            if ($ad !== null && $ad->user_id !== $initiator->id) {
                return $ad->user_id;
            }
        }

        // 2) Lease — tenant ↔ landlord (whichever is NOT the initiator).
        if (!empty($payload['lease_id'])) {
            $lease = LeaseContract::query()->find($payload['lease_id']);
            if ($lease !== null) {
                $other = $lease->user_id === $initiator->id
                    ? $lease->tenant_id
                    : $lease->user_id;
                if ($other !== null && $other !== $initiator->id) {
                    return $other;
                }
            }
        }

        // 3) Payment — the recipient (current owner of the underlying ad/agency)
        //    is approximated by the payment's user_id when it is not the initiator.
        if (!empty($payload['payment_id'])) {
            $payment = Payment::query()->find($payload['payment_id']);
            if ($payment !== null && $payment->user_id !== $initiator->id) {
                return $payment->user_id;
            }
            // Fallback: payment is initiator's own → look up the related ad's owner.
            if ($payment !== null && !empty($payment->ad_id)) {
                $ad = Ad::query()->find($payment->ad_id);
                if ($ad !== null && $ad->user_id !== $initiator->id) {
                    return $ad->user_id;
                }
            }
        }

        return null;
    }

    private function generateReference(): string
    {
        $year = now()->format('Y');
        $suffix = strtoupper(Str::random(6));

        return "KH-LITIGE-{$year}-{$suffix}";
    }

    private function notifyParties(Dispute $dispute, $notification, ?string $excludeUserId = null): void
    {
        $recipients = collect([$dispute->initiator, $dispute->respondent])
            ->filter()
            ->reject(fn (User $user): bool => $excludeUserId !== null && $user->id === $excludeUserId);

        foreach ($recipients as $recipient) {
            try {
                $recipient->notify($notification);
            } catch (Throwable $exception) {
                Log::warning('Dispute party notification failed.', [
                    'dispute_id' => $dispute->id,
                    'user_id' => $recipient->id,
                    'error' => $exception->getMessage(),
                ]);
            }
        }
    }

    private function notifyAdmins(Dispute $dispute, $notification): void
    {
        $admins = User::query()
            ->where('role', UserRole::ADMIN)
            ->where('is_active', true)
            ->whereNotNull('email')
            ->where('email', '!=', '')
            ->get();

        foreach ($admins as $admin) {
            try {
                $admin->notify($notification);
            } catch (Throwable $exception) {
                Log::warning('Dispute admin notification failed.', [
                    'dispute_id' => $dispute->id,
                    'admin_id' => $admin->id,
                    'error' => $exception->getMessage(),
                ]);
            }
        }
    }
}
