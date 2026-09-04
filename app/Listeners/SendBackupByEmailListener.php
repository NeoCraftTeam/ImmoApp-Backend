<?php

declare(strict_types=1);

namespace App\Listeners;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Spatie\Backup\Events\BackupWasSuccessful;

class SendBackupByEmailListener
{
    /**
     * Fired after Spatie Backup stores the zip on a disk.
     * When the disk is 'backups' (Cloudflare R2), generates a 48-hour signed URL
     * and emails it to BACKUP_NOTIFICATION_MAIL.
     */
    public function handle(BackupWasSuccessful $event): void
    {
        if ($event->diskName !== 'backups') {
            return;
        }

        if (!config('backup.send_backup_by_mail')) {
            return;
        }

        $to = config('backup.notifications.mail.to');
        if (empty($to)) {
            Log::warning('Backup email skipped: BACKUP_NOTIFICATION_MAIL not configured.');

            return;
        }

        $disk = Storage::disk('backups');

        $files = collect($disk->files($event->backupName))
            ->filter(fn (string $f) => str_ends_with($f, '.zip'))
            ->sortDesc();

        $path = $files->first();

        if ($path === null) {
            Log::warning('Backup email skipped: no zip found in '.$event->backupName.' on backups disk.');

            return;
        }

        $sizeInMb = round(($disk->size($path)) / 1024 / 1024, 2);
        $expiresAt = now()->addHours(48);

        try {
            $signedUrl = $disk->temporaryUrl($path, $expiresAt);
        } catch (\Throwable $e) {
            Log::error('Backup email: failed to generate R2 signed URL.', ['error' => $e->getMessage()]);

            return;
        }

        $appName = config('app.name');
        $subject = "[{$appName}] Sauvegarde réussie — ".now()->format('d/m/Y H:i');

        Mail::send([], [], function ($message) use ($to, $subject, $signedUrl, $sizeInMb, $expiresAt, $path): void {
            $message->to($to)
                ->subject($subject)
                ->html(implode('', [
                    '<p><strong>Sauvegarde réussie.</strong></p>',
                    '<ul>',
                    '<li><strong>Fichier :</strong> '.basename($path).'</li>',
                    '<li><strong>Taille :</strong> '.$sizeInMb.' MB</li>',
                    '<li><strong>Lien (valide 48h) :</strong> <a href="'.$signedUrl.'">Télécharger le backup</a></li>',
                    '<li><strong>Expire le :</strong> '.$expiresAt->format('d/m/Y H:i').' UTC</li>',
                    '</ul>',
                    '<p style="color:#888;font-size:12px;">Ce lien est privé — ne pas partager.</p>',
                ]));
        });
    }
}
