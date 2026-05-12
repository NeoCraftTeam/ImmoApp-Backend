<?php

declare(strict_types=1);

namespace App\Filament\Admin\Pages;

use App\Enums\AdminPermission;
use App\Models\QueueFailedJob;
use Filament\Actions\Action as HeaderAction;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
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
    public static function canAccess(): bool
    {
        return auth()->user()?->hasAdminPermission(AdminPermission::JobsMonitor) ?? false;
    }

    #[\Override]
    protected function getHeaderActions(): array
    {
        return [
            HeaderAction::make('retryAll')
                ->label('Tout relancer')
                ->color('warning')
                ->icon('heroicon-o-arrow-path')
                ->requiresConfirmation()
                ->modalHeading('Relancer tous les jobs échoués')
                ->modalDescription('Tous les jobs présents dans la table failed_jobs seront ré-introduits dans la file d\'attente.')
                ->action(function (): void {
                    Artisan::call('queue:retry', ['id' => ['all']]);
                    Notification::make()
                        ->title('Tous les jobs échoués ont été relancés.')
                        ->success()
                        ->send();
                    $this->resetTable();
                }),
            HeaderAction::make('flushAll')
                ->label('Tout purger')
                ->color('danger')
                ->icon('heroicon-o-trash')
                ->requiresConfirmation()
                ->modalHeading('Supprimer définitivement tous les jobs échoués')
                ->modalDescription('Cette action est irréversible. Les jobs ne pourront plus être relancés.')
                ->action(function (): void {
                    Artisan::call('queue:flush');
                    Notification::make()
                        ->title('Tous les jobs échoués ont été purgés.')
                        ->success()
                        ->send();
                    $this->resetTable();
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
                TextColumn::make('failed_at')
                    ->label('Échoué le')
                    ->dateTime('d/m/Y H:i:s')
                    ->sortable()
                    ->size('sm'),
                TextColumn::make('queue')
                    ->label('File')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'critical' => 'danger',
                        'payments' => 'warning',
                        'emails' => 'info',
                        'tours' => 'success',
                        default => 'gray',
                    })
                    ->sortable(),
                TextColumn::make('connection')
                    ->label('Connexion')
                    ->size('sm')
                    ->color('gray')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('job_class')
                    ->label('Job')
                    ->getStateUsing(function (QueueFailedJob $record): string {
                        $data = json_decode($record->payload, true);

                        return class_basename($data['displayName'] ?? 'Unknown');
                    })
                    ->searchable(query: fn ($query, string $search) => $query->where('payload', 'ilike', "%{$search}%"))
                    ->wrap(),
                TextColumn::make('exception_summary')
                    ->label('Erreur')
                    ->getStateUsing(fn (QueueFailedJob $record): string => self::firstLineOfException((string) $record->exception))
                    ->limit(80)
                    ->wrap()
                    ->color('danger')
                    ->size('sm'),
                TextColumn::make('uuid')
                    ->label('UUID')
                    ->size('sm')
                    ->copyable()
                    ->copyMessage('UUID copié')
                    ->limit(8)
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('queue')
                    ->label('File')
                    ->options(fn (): array => QueueFailedJob::query()
                        ->select('queue')
                        ->distinct()
                        ->orderBy('queue')
                        ->pluck('queue', 'queue')
                        ->all())
                    ->native(false),
                SelectFilter::make('connection')
                    ->label('Connexion')
                    ->options(fn (): array => QueueFailedJob::query()
                        ->select('connection')
                        ->distinct()
                        ->orderBy('connection')
                        ->pluck('connection', 'connection')
                        ->all())
                    ->native(false),
            ])
            ->recordActions([
                HeaderAction::make('view')
                    ->label('Détails')
                    ->icon('heroicon-o-eye')
                    ->color('gray')
                    ->slideOver()
                    ->modalWidth('4xl')
                    ->modalHeading(fn (QueueFailedJob $record): string => 'Détails du job — '.class_basename(self::resolveJobName($record)))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Fermer')
                    ->modalContent(fn (QueueFailedJob $record) => view('filament.admin.components.failed-job-details', [
                        'record' => $record,
                        'payloadDecoded' => json_decode((string) $record->payload, true) ?: [],
                    ])),
                HeaderAction::make('retry')
                    ->label('Relancer')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading('Relancer ce job')
                    ->action(function (QueueFailedJob $record): void {
                        Artisan::call('queue:retry', ['id' => [$record->uuid]]);
                        Notification::make()->title('Job relancé.')->success()->send();
                        $this->resetTable();
                    }),
                HeaderAction::make('delete')
                    ->label('Supprimer')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Supprimer définitivement ce job')
                    ->action(function (QueueFailedJob $record): void {
                        DB::table('failed_jobs')->where('uuid', $record->uuid)->delete();
                        Notification::make()->title('Job supprimé.')->success()->send();
                        $this->resetTable();
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('retrySelected')
                        ->label('Relancer la sélection')
                        ->icon('heroicon-o-arrow-path')
                        ->color('warning')
                        ->requiresConfirmation()
                        ->action(function (EloquentCollection $records): void {
                            $uuids = $records->pluck('uuid')->all();
                            if ($uuids === []) {
                                return;
                            }
                            Artisan::call('queue:retry', ['id' => $uuids]);
                            Notification::make()
                                ->title(count($uuids).' job(s) relancé(s).')
                                ->success()
                                ->send();
                            $this->resetTable();
                        }),
                    BulkAction::make('deleteSelected')
                        ->label('Supprimer la sélection')
                        ->icon('heroicon-o-trash')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->action(function (EloquentCollection $records): void {
                            $uuids = $records->pluck('uuid')->all();
                            DB::table('failed_jobs')->whereIn('uuid', $uuids)->delete();
                            Notification::make()
                                ->title(count($uuids).' job(s) supprimé(s).')
                                ->success()
                                ->send();
                            $this->resetTable();
                        }),
                ]),
            ])
            ->emptyStateHeading('Aucun job échoué')
            ->emptyStateDescription('Tous les jobs de la file d\'attente fonctionnent normalement.')
            ->emptyStateIcon('heroicon-o-check-circle')
            ->poll('30s');
    }

    private static function firstLineOfException(string $exception): string
    {
        $line = strtok($exception, "\n");

        return $line === false ? $exception : $line;
    }

    private static function resolveJobName(QueueFailedJob $record): string
    {
        $data = json_decode((string) $record->payload, true);

        return $data['displayName'] ?? 'Job inconnu';
    }
}
