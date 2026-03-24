<?php

declare(strict_types=1);

namespace App\Filament\Admin\Pages;

use App\Models\QueueFailedJob;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use UnitEnum;

class FailedJobsMonitor extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedExclamationTriangle;

    protected static ?string $navigationLabel = 'Failed Jobs';

    protected static string|null|UnitEnum $navigationGroup = 'System';

    protected static ?int $navigationSort = 51;

    protected string $view = 'filament.admin.pages.failed-jobs-monitor';

    #[\Override]
    protected function getHeaderActions(): array
    {
        return [
            Action::make('retryAll')
                ->label('Retry All')
                ->color('warning')
                ->icon('heroicon-o-arrow-path')
                ->requiresConfirmation()
                ->action(function (): void {
                    Artisan::call('queue:retry', ['id' => ['all']]);
                    Notification::make()->title('All failed jobs queued for retry.')->success()->send();
                }),
            Action::make('flushAll')
                ->label('Flush All')
                ->color('danger')
                ->icon('heroicon-o-trash')
                ->requiresConfirmation()
                ->action(function (): void {
                    Artisan::call('queue:flush');
                    Notification::make()->title('All failed jobs flushed.')->success()->send();
                }),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                fn (): Builder => QueueFailedJob::query()
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
                Action::make('retry')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->action(function (QueueFailedJob $record): void {
                        Artisan::call('queue:retry', ['id' => [$record->uuid]]);
                        Notification::make()->title('Job queued for retry.')->success()->send();
                    }),
                Action::make('delete')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(function (QueueFailedJob $record): void {
                        DB::table('failed_jobs')->where('uuid', $record->uuid)->delete();
                        Notification::make()->title('Failed job deleted.')->success()->send();
                    }),
            ])
            ->emptyStateHeading('No failed jobs')
            ->emptyStateDescription('All queue jobs are processing normally.')
            ->emptyStateIcon('heroicon-o-check-circle')
            ->poll('30s');
    }
}
