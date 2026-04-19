<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Message;
use App\Services\Chat\AttachmentService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Refresh signed URLs for attachments older than 20 hours.
 * Scheduled daily to ensure no signed URL expires before the next run.
 */
final class CleanExpiredSignedUrlsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function handle(AttachmentService $service): void
    {
        $threshold = now()->subHours(20);

        Message::whereNotNull('attachments')
            ->whereNull('deleted_at')
            ->where('created_at', '<=', $threshold)
            ->chunk(100, function ($messages) use ($service): void {
                foreach ($messages as $message) {
                    $attachments = $message->attachments;
                    if (! is_array($attachments) || $attachments === []) {
                        continue;
                    }

                    $refreshed = false;
                    foreach ($attachments as &$attachment) {
                        if (! isset($attachment['url'])) {
                            continue;
                        }

                        try {
                            $attachment['signed_url'] = $service->getSignedUrl($attachment['url']);
                            $refreshed                = true;
                        } catch (\Throwable $e) {
                            Log::warning('[Chat] Failed to refresh signed URL', [
                                'message_id' => $message->id,
                                'path'       => $attachment['url'],
                                'error'      => $e->getMessage(),
                            ]);
                        }
                    }
                    unset($attachment);

                    if ($refreshed) {
                        $message->update(['attachments' => $attachments]);
                    }
                }
            });
    }
}
