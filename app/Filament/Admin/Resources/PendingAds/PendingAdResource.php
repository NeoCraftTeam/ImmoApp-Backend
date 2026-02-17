<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\PendingAds;

use App\Enums\AdStatus;
use App\Filament\Admin\Resources\PendingAds\Pages\ManagePendingAds;
use App\Filament\Resources\Ads\Concerns\SharedAdResource;
use App\Mail\AdApprovedMail;
use App\Mail\AdDeclinedMail;
use App\Models\Ad;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\SpatieMediaLibraryImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use UnitEnum;

class PendingAdResource extends Resource
{
    use SharedAdResource;

    protected static ?string $model = Ad::class;

    protected static string|null|UnitEnum $navigationGroup = 'Annonces';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    protected static ?string $navigationLabel = 'À valider';

    protected static ?string $modelLabel = 'Annonce en attente';

    protected static ?string $pluralModelLabel = 'Annonces en attente';

    protected static ?int $navigationSort = 0;

    protected static ?string $slug = 'pending-ads';

    /**
     * Scope to only pending ads.
     */
    #[\Override]
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('status', AdStatus::PENDING)
            ->with(['user', 'ad_type', 'quarter.city', 'media'])
            ->latest();
    }

    public static function canCreate(): bool
    {
        return false;
    }

    #[\Override]
    public static function infolist(Schema $schema): Schema
    {
        return $schema->components(static::getSharedInfolistSchema(showMeta: true));
    }

    #[\Override]
    public static function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('title')
            ->columns([
                SpatieMediaLibraryImageColumn::make('images')
                    ->collection('images')
                    ->label('Photo')
                    ->limit(1)
                    ->circular()
                    ->size(40),
                TextColumn::make('title')
                    ->label('Titre')
                    ->limit(50)
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                TextColumn::make('user.firstname')
                    ->label('Soumis par')
                    ->formatStateUsing(fn ($record) => $record->user->fullname ?? 'Inconnu')
                    ->searchable(['firstname', 'lastname']),
                TextColumn::make('price')
                    ->label('Prix')
                    ->money('XAF')
                    ->sortable(),
                TextColumn::make('ad_type.name')
                    ->label('Type')
                    ->badge()
                    ->color('info'),
                TextColumn::make('quarter.city.name')
                    ->label('Ville')
                    ->sortable(),
                TextColumn::make('surface_area')
                    ->label('Surface')
                    ->suffix(' m²')
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('Soumis le')
                    ->since()
                    ->sortable(),
            ])
            ->recordActions([
                ViewAction::make(),

                // ── Approuver ──
                Action::make('approve')
                    ->label('Approuver')
                    ->icon('heroicon-m-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalIcon('heroicon-o-check-circle')
                    ->modalIconColor('success')
                    ->modalHeading('Approuver cette annonce')
                    ->modalDescription(fn (Ad $record) => "L'annonce \"{$record->title}\" sera publiée et un email de confirmation sera envoyé à l'auteur.")
                    ->action(function (Ad $record): void {
                        $record->update(['status' => AdStatus::AVAILABLE]);

                        // Send approval email to author
                        if ($record->user) {
                            try {
                                Mail::to($record->user)->send(new AdApprovedMail($record));
                            } catch (\Throwable $e) {
                                Log::error('Failed to send ad approval email: '.$e->getMessage());
                            }
                        }

                        Notification::make()
                            ->success()
                            ->title('Annonce approuvée ✅')
                            ->body("\"{$record->title}\" est maintenant visible. Un email a été envoyé à l'auteur.")
                            ->send();
                    }),

                // ── Décliner ──
                Action::make('decline')
                    ->label('Décliner')
                    ->icon('heroicon-m-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalIcon('heroicon-o-x-circle')
                    ->modalIconColor('danger')
                    ->modalHeading('Décliner cette annonce')
                    ->modalDescription(fn (Ad $record) => "L'annonce \"{$record->title}\" sera supprimée et l'auteur sera notifié par email.")
                    ->form([
                        Textarea::make('reason')
                            ->label('Motif du refus (optionnel)')
                            ->placeholder('Ex: Photos floues, description incomplète, prix irréaliste…')
                            ->rows(3),
                    ])
                    ->action(function (Ad $record, array $data): void {
                        $reason = $data['reason'] ?? '';

                        // Send decline email before deleting
                        if ($record->user) {
                            try {
                                Mail::to($record->user)->send(new AdDeclinedMail($record, $reason));
                            } catch (\Throwable $e) {
                                Log::error('Failed to send ad decline email: '.$e->getMessage());
                            }
                        }

                        $title = $record->title;
                        $record->delete();

                        Notification::make()
                            ->danger()
                            ->title('Annonce déclinée ❌')
                            ->body("\"{$title}\" a été supprimée. Un email a été envoyé à l'auteur.")
                            ->send();
                    }),
            ])
            ->emptyStateHeading('Aucune annonce en attente')
            ->emptyStateDescription('Toutes les annonces ont été traitées. 🎉')
            ->emptyStateIcon('heroicon-o-check-circle')
            ->poll('15s');
    }

    public static function getPages(): array
    {
        return [
            'index' => ManagePendingAds::route('/'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        $count = Ad::where('status', AdStatus::PENDING)->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Annonces à valider';
    }
}
