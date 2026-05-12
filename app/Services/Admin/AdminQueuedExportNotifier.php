<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Models\AdminQueuedExport;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\URL;

final class AdminQueuedExportNotifier
{
    public static function notifyExportReady(User $user, AdminQueuedExport $export): void
    {
        $url = URL::temporarySignedRoute(
            'admin.queued-exports.download',
            now()->addHours(24),
            ['export' => $export->getKey()],
        );

        Notification::make()
            ->success()
            ->title('Export prêt')
            ->body('Votre fichier « '.$export->download_name.' » est disponible (lien valable 24 h).')
            ->icon(Heroicon::ArrowDownTray)
            ->actions([
                Action::make('download')
                    ->label('Télécharger')
                    ->url($url)
                    ->button(),
            ])
            ->sendToDatabase($user);
    }

    public static function notifyExportFailed(User $user): void
    {
        Notification::make()
            ->danger()
            ->title('Export échoué')
            ->body('Une erreur est survenue lors de la génération. Réessayez ou contactez le support.')
            ->sendToDatabase($user);
    }
}
