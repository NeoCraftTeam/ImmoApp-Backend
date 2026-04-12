<?php

declare(strict_types=1);

namespace App\Filament\Admin\Pages;

use App\Models\QueueFailedJob;
use Filament\Actions\Action as HeaderAction;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use UnitEnum;

class FailedJobsMonitor extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedExclamationTriangle;

    protected static ?string $navigationLabel = 'Jobs échoués';

    protected static string|null|UnitEnum $navigationGroup = 'Configuration';

    protected static ?int $navigationSort = 51;

    protected string $view = 'filament.admin.pages.failed-jobs-monitor';

    #[\Override]
    protected function getHeaderActions(): array
    {
        return [
            HeaderAction::make('retryAll')
                ->label('Tout relancer')
                ->color('warning')
                ->icon('heroicon-o-arrow-path')
                ->requiresConfirmation()
                ->action(function (): void {
                    Artisan::call('queue:retry', ['id' => ['all']]);
                    Notification::make()->title('Tous les jobs échoués ont été relancés.')->success()->send();
                }),
            HeaderAction::make('flushAll')
                ->label('Tout purger')
                ->color('danger')
                ->icon('heroicon-o-trash')
                ->requiresConfirmation()
                ->action(function (): void {
                    Artisan::call('queue:flush');
                    Notification::make()->title('Tous les jobs échoués ont été purgés.')->success()->send();
                }),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                QueueFailedJob::query()
                    ->select(['id', 'uuid', 'connection', 'queue', 'payload', 'exception', 'failed_at'])
                    ->orderByDesc('failed_at')
            )
            ->columns([
                TextColumn::make('uuid')
                    ->label('UUID')
                    ->searchable()
                    ->copyable()
                    ->limit(12),
                TextColumn::make('queue')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'critical' => 'danger',
                        'payments' => 'warning',
                        'emails' => 'info',
                        default => 'gray',
                    }),
                TextColumn::make('payload')
                    ->label('Job')
                    ->formatStateUsing(function (string $state): string {
                        $data = json_decode($state, true);

                        return class_basename($data['displayName'] ?? 'Unknown');
                    })
                    ->searchable(),
                TextColumn::make('exception')
                    ->limit(80)
                    ->tooltip(fn (string $state): string => $state)
                    ->wrap(),
                TextColumn::make('failed_at')
                    ->dateTime('Y-m-d H:i')
                    ->sortable(),
            ])
            ->actions([
                HeaderAction::make('retry')
                    ->label('Relancer')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->action(function (QueueFailedJob $record): void {
                        Artisan::call('queue:retry', ['id' => [$record->uuid]]);
                        Notification::make()->title('Job relancé avec succès.')->success()->send();
                    }),
                HeaderAction::make('delete')
                    ->label('Supprimer')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(function (QueueFailedJob $record): void {
                        DB::table('failed_jobs')->where('uuid', $record->uuid)->delete();
                        Notification::make()->title('Job supprimé.')->success()->send();
                    }),
            ])
            ->emptyStateHeading('Aucun job échoué')
            ->emptyStateDescription('Tous les jobs de la file d\'attente fonctionnent normalement.')
            ->emptyStateIcon('heroicon-o-check-circle')
            ->poll('30s');
    }
}
