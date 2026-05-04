<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\AdReports\Pages;

use App\Enums\AdReportStatus;
use App\Filament\Admin\Resources\AdReports\AdReportResource;
use App\Models\AdReport;
use App\Notifications\AdHiddenAfterReportNotification;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\DB;

class EditAdReport extends EditRecord
{
    protected static string $resource = AdReportResource::class;

    /**
     * Pre-defined French moderation reasons offered to the admin in the
     * "Hide ad" modal. Free-text addendum is appended via the textarea below.
     *
     * @var array<string, string>
     */
    private const array MODERATION_REASONS = [
        'fake_listing' => 'Annonce frauduleuse ou trompeuse',
        'inappropriate_content' => 'Contenu inapproprié ou offensant',
        'spam' => 'Spam ou contenu commercial non autorisé',
        'duplicate' => 'Annonce en doublon',
        'wrong_category' => 'Catégorie / type d\'annonce incorrect',
        'illegal_content' => 'Contenu illégal ou non conforme',
        'misleading_price' => 'Prix manifestement incorrect ou trompeur',
        'low_quality_photos' => 'Photos absentes ou de qualité insuffisante',
        'other' => 'Autre motif (précisé ci-dessous)',
    ];

    #[\Override]
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $nextStatus = $data['status'] instanceof AdReportStatus
            ? $data['status']
            : AdReportStatus::from($data['status']);
        $isClosingStatus = in_array($nextStatus, [AdReportStatus::RESOLVED, AdReportStatus::DISMISSED], true);

        if ($isClosingStatus) {
            $data['resolved_at'] = now();
            $data['resolved_by'] = auth()->id();
        } else {
            $data['resolved_at'] = null;
            $data['resolved_by'] = null;
        }

        return $data;
    }

    #[\Override]
    protected function getHeaderActions(): array
    {
        /** @var AdReport $report */
        $report = $this->record;
        $alreadyClosed = in_array($report->status, [AdReportStatus::RESOLVED, AdReportStatus::DISMISSED], true);
        $adAlreadyHidden = $report->ad && !$report->ad->is_visible;

        return [
            Action::make('hideAd')
                ->label('Masquer l\'annonce')
                ->icon(Heroicon::OutlinedEyeSlash)
                ->color('danger')
                ->visible(fn (): bool => !$alreadyClosed && !$adAlreadyHidden && $report->ad !== null)
                ->modalIcon(Heroicon::OutlinedShieldExclamation)
                ->modalHeading('Masquer l\'annonce signalée')
                ->modalDescription('L\'annonce sera retirée des résultats de recherche et un email sera envoyé au propriétaire avec le motif que vous indiquez.')
                ->modalSubmitActionLabel('Masquer et notifier le propriétaire')
                ->schema([
                    Select::make('reason_key')
                        ->label('Motif principal')
                        ->options(self::MODERATION_REASONS)
                        ->required()
                        ->native(false),
                    Textarea::make('reason_detail')
                        ->label('Précisions (optionnel)')
                        ->rows(4)
                        ->placeholder('Détail à transmettre au propriétaire pour qu\'il puisse corriger son annonce...')
                        ->maxLength(1000),
                ])
                ->action(function (array $data) use ($report): void {
                    $reasonLabel = self::MODERATION_REASONS[$data['reason_key']] ?? 'Motif non précisé';
                    $detail = trim((string) ($data['reason_detail'] ?? ''));
                    $fullReason = $detail !== '' ? $reasonLabel.' — '.$detail : $reasonLabel;

                    DB::transaction(function () use ($report, $fullReason): void {
                        if ($report->ad) {
                            $report->ad->update(['is_visible' => false]);
                        }

                        $report->update([
                            'status' => AdReportStatus::RESOLVED,
                            'admin_notes' => trim(($report->admin_notes ? $report->admin_notes."\n\n" : '').'[Annonce masquée] '.$fullReason),
                            'resolved_at' => now(),
                            'resolved_by' => auth()->id(),
                        ]);
                    });

                    if ($report->ad && $report->ad->user) {
                        $report->ad->user->notify(new AdHiddenAfterReportNotification($report->ad, $fullReason));
                    }

                    Notification::make()
                        ->title('Annonce masquée')
                        ->body('Le propriétaire a été notifié par email.')
                        ->success()
                        ->send();

                    $this->redirect($this::getResource()::getUrl('index'));
                }),

            Action::make('dismissReport')
                ->label('Classer sans suite')
                ->icon(Heroicon::OutlinedArchiveBoxXMark)
                ->color('gray')
                ->visible(fn (): bool => !$alreadyClosed)
                ->modalIcon(Heroicon::OutlinedArchiveBoxXMark)
                ->modalHeading('Classer le signalement sans suite')
                ->modalDescription('Le signalement sera marqué comme rejeté. L\'annonce reste publiée et le propriétaire n\'est pas notifié.')
                ->modalSubmitActionLabel('Classer le signalement')
                ->schema([
                    Textarea::make('admin_notes')
                        ->label('Note interne (optionnelle)')
                        ->rows(4)
                        ->placeholder('Pourquoi ce signalement est-il rejeté ? (visible uniquement par les admins)')
                        ->maxLength(1000),
                ])
                ->action(function (array $data) use ($report): void {
                    $note = trim((string) ($data['admin_notes'] ?? ''));

                    $report->update([
                        'status' => AdReportStatus::DISMISSED,
                        'admin_notes' => trim(($report->admin_notes ? $report->admin_notes."\n\n" : '').'[Classé sans suite] '.($note !== '' ? $note : 'Aucune note interne')),
                        'resolved_at' => now(),
                        'resolved_by' => auth()->id(),
                    ]);

                    Notification::make()
                        ->title('Signalement classé sans suite')
                        ->success()
                        ->send();

                    $this->redirect($this::getResource()::getUrl('index'));
                }),

            DeleteAction::make(),
        ];
    }
}
