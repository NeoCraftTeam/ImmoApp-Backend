<?php

declare(strict_types=1);

namespace App\Listeners;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Spatie\Backup\Events\BackupZipWasCreated;

class SendBackupByEmailListener
{
    /**
     * Send the backup zip by email when enabled.
     * Skips attachment if file exceeds max size (most providers limit to ~25MB).
     */
    public function handle(BackupZipWasCreated $event): void
    {
        if (!config('backup.send_backup_by_mail', false)) {
            return;
        }

        $path = $event->pathToZip;
        if (!is_file($path)) {
            return;
        }

        $maxSizeMb = (int) config('backup.send_backup_max_size_mb', 20);
        $maxSizeBytes = $maxSizeMb * 1024 * 1024;
        $size = filesize($path);

        $to = config('backup.notifications.mail.to');
        if (empty($to)) {
            Log::warning('Backup email skipped: BACKUP_NOTIFICATION_MAIL not configured');

            return;
        }

        $subject = '['.config('app.name').'] Backup successful';
        $attachFile = $size <= $maxSizeBytes;

        Mail::send([], [], function ($message) use ($path, $to, $subject, $attachFile, $size): void {
            $message->to($to)
                ->subject($subject)
                ->html(sprintf(
                    'Backup completed at %s.<br>Size: %s MB.%s',
                    now()->toDateTimeString(),
                    round($size / 1024 / 1024, 2),
                    $attachFile ? '' : ' Attachment skipped (exceeds max size).',
                ));

            if ($attachFile) {
                $message->attach($path, ['as' => basename($path)]);
            }
        });
    }
}
