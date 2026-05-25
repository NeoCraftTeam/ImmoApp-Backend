<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Disputes\Pages;

use App\Enums\DisputeStatus;
use App\Filament\Admin\Resources\Disputes\DisputeResource;
use App\Models\Dispute;
use App\Services\DisputeService;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;
use Throwable;

class EditDispute extends EditRecord
{
    protected static string $resource = DisputeResource::class;

    #[\Override]
    protected function getHeaderActions(): array
    {
        /** @var Dispute $dispute */
        $dispute = $this->record;

        return [
            $this->buildTransitionAction(
                $dispute,
                DisputeStatus::UNDER_REVIEW,
                'Prendre en charge',
                Heroicon::OutlinedHandRaised,
                'warning',
                requiresNote: false,
            ),
            $this->buildTransitionAction(
                $dispute,
                DisputeStatus::MEDIATION,
                'Passer en médiation',
                Heroicon::OutlinedChatBubbleLeftRight,
                'info',
                requiresNote: false,
            ),
            $this->buildTransitionAction(
                $dispute,
                DisputeStatus::RESOLVED_INITIATOR,
                'Résoudre en faveur de l\'initiateur',
                Heroicon::OutlinedCheckBadge,
                'success',
                requiresNote: true,
            ),
            $this->buildTransitionAction(
                $dispute,
                DisputeStatus::RESOLVED_RESPONDENT,
                'Résoudre en faveur du défendeur',
                Heroicon::OutlinedCheckBadge,
                'success',
                requiresNote: true,
            ),
            $this->buildTransitionAction(
                $dispute,
                DisputeStatus::RESOLVED_AMICABLY,
                'Résolu à l\'amiable',
                Heroicon::OutlinedHandThumbUp,
                'success',
                requiresNote: true,
            ),
            $this->buildTransitionAction(
                $dispute,
                DisputeStatus::REJECTED,
                'Rejeter',
                Heroicon::OutlinedXCircle,
                'danger',
                requiresNote: true,
            ),
        ];
    }

    private function buildTransitionAction(
        Dispute $dispute,
        DisputeStatus $target,
        string $label,
        Heroicon $icon,
        string $color,
        bool $requiresNote,
    ): Action {
        $action = Action::make('transition_to_'.$target->value)
            ->label($label)
            ->icon($icon)
            ->color($color)
            ->visible(fn (): bool => $dispute->status->canTransitionTo($target))
            ->modalHeading($label)
            ->modalSubmitActionLabel($label);

        if ($requiresNote) {
            $action = $action->schema([
                Textarea::make('resolution_note')
                    ->label('Note de résolution')
                    ->rows(4)
                    ->required()
                    ->maxLength(5000),
            ]);
        }

        return $action->action(function (array $data) use ($dispute, $target): void {
            try {
                app(DisputeService::class)->transition(
                    $dispute->fresh(),
                    auth()->user(),
                    $target,
                    $data['resolution_note'] ?? null,
                );
            } catch (Throwable $e) {
                Notification::make()
                    ->title('Transition refusée')
                    ->body($e->getMessage())
                    ->danger()
                    ->send();

                return;
            }

            Notification::make()
                ->title('Litige mis à jour')
                ->success()
                ->send();

            $this->refreshFormData(['status', 'resolution_note', 'resolved_at', 'admin_id']);
        });
    }
}
