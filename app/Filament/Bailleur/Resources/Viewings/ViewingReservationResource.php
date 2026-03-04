<?php

declare(strict_types=1);

namespace App\Filament\Bailleur\Resources\Viewings;

use App\Enums\CancelledBy;
use App\Enums\ReservationStatus;
use App\Filament\Bailleur\Resources\Viewings\Pages\ManageViewingReservations;
use App\Models\Ad;
use App\Models\TentativeReservation;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Textarea;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class ViewingReservationResource extends Resource
{
    protected static ?string $model = TentativeReservation::class;

    protected static string|null|UnitEnum $navigationGroup = 'Visites';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static ?string $navigationLabel = 'Demandes de visite';

    protected static ?string $modelLabel = 'Demande';

    protected static ?string $pluralModelLabel = 'Demandes de visite';

    protected static ?int $navigationSort = 1;

    #[\Override]
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->whereHas('ad', fn (Builder $q) => $q->where('user_id', auth()->id()))
            ->with(['ad', 'client']);
    }

    public static function getNavigationBadge(): ?string
    {
        $count = static::getEloquentQuery()
            ->where('status', ReservationStatus::Pending)
            ->count();

        return $count > 0 ? (string) $count : null;
    }

    #[\Override]
    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Demandes en attente';
    }

    #[\Override]
    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    #[\Override]
    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Créneau demandé')
                    ->icon('heroicon-o-calendar-days')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('slot_date')
                            ->label('Date')
                            ->date('l d F Y')
                            ->icon('heroicon-o-calendar')
                            ->weight(FontWeight::SemiBold),
                        TextEntry::make('slot_starts_at')
                            ->label('De')
                            ->icon('heroicon-o-clock'),
                        TextEntry::make('slot_ends_at')
                            ->label('À')
                            ->icon('heroicon-o-clock'),
                        TextEntry::make('status')
                            ->label('Statut')
                            ->badge()
                            ->formatStateUsing(fn (ReservationStatus $state) => $state->label())
                            ->color(fn (ReservationStatus $state) => match ($state) {
                                ReservationStatus::Pending => 'warning',
                                ReservationStatus::Confirmed => 'success',
                                ReservationStatus::Cancelled => 'danger',
                                ReservationStatus::Expired => 'gray',
                            }),
                        TextEntry::make('expires_at')
                            ->label('Expire le')
                            ->dateTime('d/m/Y à H:i')
                            ->icon('heroicon-o-exclamation-circle')
                            ->color(fn (TentativeReservation $record) => $record->isExpired() ? 'danger' : 'warning'),
                    ]),

                Section::make('Locataire')
                    ->icon('heroicon-o-user-circle')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('client.firstname')
                            ->label('Prénom')
                            ->icon('heroicon-o-user'),
                        TextEntry::make('client.lastname')
                            ->label('Nom')
                            ->icon('heroicon-o-user'),
                        TextEntry::make('client.email')
                            ->label('Email')
                            ->icon('heroicon-o-envelope')
                            ->copyable(),
                        TextEntry::make('client.phone')
                            ->label('Téléphone')
                            ->icon('heroicon-o-phone')
                            ->placeholder('Non renseigné')
                            ->copyable(),
                        TextEntry::make('client_message')
                            ->label('Message du locataire')
                            ->placeholder('Aucun message')
                            ->columnSpanFull()
                            ->icon('heroicon-o-chat-bubble-left-ellipsis'),
                    ]),

                Section::make('Annonce concernée')
                    ->icon('heroicon-o-home')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('ad.title')
                            ->label('Titre')
                            ->icon('heroicon-o-document-text'),
                        TextEntry::make('ad.price')
                            ->label('Loyer')
                            ->money('XAF')
                            ->icon('heroicon-o-banknotes'),
                    ]),

                Section::make('Notes bailleur')
                    ->icon('heroicon-o-pencil-square')
                    ->collapsible()
                    ->schema([
                        TextEntry::make('landlord_notes')
                            ->label('Mes notes')
                            ->placeholder('Aucune note')
                            ->columnSpanFull(),
                    ]),

                Section::make('Annulation')
                    ->icon('heroicon-o-x-circle')
                    ->collapsible()
                    ->collapsed()
                    ->visible(fn (TentativeReservation $record) => $record->status === ReservationStatus::Cancelled)
                    ->schema([
                        TextEntry::make('cancelled_by')
                            ->label('Annulé par')
                            ->formatStateUsing(fn (?CancelledBy $state) => match ($state) {
                                CancelledBy::Client => 'Le locataire',
                                CancelledBy::Landlord => 'Vous (bailleur)',
                                CancelledBy::System => 'Le système',
                                default => '—',
                            })
                            ->badge()
                            ->color(fn (?CancelledBy $state) => match ($state) {
                                CancelledBy::Landlord => 'warning',
                                CancelledBy::Client => 'info',
                                default => 'gray',
                            }),
                        TextEntry::make('cancellation_reason')
                            ->label('Motif')
                            ->placeholder('Aucun motif renseigné')
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    #[\Override]
    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('slot_date', 'asc')
            ->columns([
                TextColumn::make('slot_date')
                    ->label('Date de visite')
                    ->date('d/m/Y')
                    ->sortable()
                    ->icon('heroicon-o-calendar')
                    ->weight(FontWeight::SemiBold),

                TextColumn::make('slot_starts_at')
                    ->label('Horaire')
                    ->formatStateUsing(fn (TentativeReservation $record) => $record->slot_starts_at.' – '.$record->slot_ends_at)
                    ->icon('heroicon-o-clock'),

                TextColumn::make('ad.title')
                    ->label('Annonce')
                    ->limit(30)
                    ->searchable()
                    ->tooltip(fn (TentativeReservation $record) => $record->ad->title),

                TextColumn::make('client.firstname')
                    ->label('Locataire')
                    ->formatStateUsing(fn (TentativeReservation $record) => $record->client->firstname.' '.$record->client->lastname)
                    ->icon('heroicon-o-user'),

                TextColumn::make('status')
                    ->label('Statut')
                    ->badge()
                    ->formatStateUsing(fn (ReservationStatus $state) => $state->label())
                    ->color(fn (ReservationStatus $state) => match ($state) {
                        ReservationStatus::Pending => 'warning',
                        ReservationStatus::Confirmed => 'success',
                        ReservationStatus::Cancelled => 'danger',
                        ReservationStatus::Expired => 'gray',
                    })
                    ->sortable(),

                TextColumn::make('expires_at')
                    ->label('Expire')
                    ->since()
                    ->sortable()
                    ->color(fn (TentativeReservation $record) => $record->isExpired() ? 'danger' : null)
                    ->tooltip(fn (TentativeReservation $record) => $record->expires_at->format('d/m/Y à H:i'))
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Statut')
                    ->options(
                        collect(ReservationStatus::cases())
                            ->mapWithKeys(fn (ReservationStatus $s) => [$s->value => $s->label()])
                            ->toArray()
                    ),

                SelectFilter::make('ad_id')
                    ->label('Annonce')
                    ->options(
                        fn () => Ad::query()
                            ->where('user_id', auth()->id())
                            ->pluck('title', 'id')
                            ->toArray()
                    )
                    ->searchable(),
            ])
            ->actions([
                ViewAction::make()
                    ->slideOver()
                    ->modalWidth('xl'),

                Action::make('confirm')
                    ->label('Confirmer')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (TentativeReservation $record) => $record->status === ReservationStatus::Pending)
                    ->requiresConfirmation()
                    ->modalHeading('Confirmer la visite')
                    ->modalDescription(fn (TentativeReservation $record) => "Confirmer la visite du {$record->slot_date->format('d/m/Y')} à {$record->slot_starts_at} pour {$record->client->firstname} {$record->client->lastname} ?")
                    ->modalIconColor('success')
                    ->action(function (TentativeReservation $record): void {
                        $record->update(['status' => ReservationStatus::Confirmed]);

                        Notification::make()
                            ->title('Visite confirmée')
                            ->body("La visite du {$record->slot_date->format('d/m/Y')} a été confirmée.")
                            ->success()
                            ->send();
                    }),

                Action::make('addNotes')
                    ->label('Notes')
                    ->icon('heroicon-o-pencil-square')
                    ->color('gray')
                    ->slideOver()
                    ->modalHeading('Notes bailleur')
                    ->form([
                        Textarea::make('landlord_notes')
                            ->label('Mes notes (visibles uniquement par vous)')
                            ->rows(5)
                            ->placeholder('Ex: Le locataire semble sérieux, a demandé des informations sur le bail…'),
                    ])
                    ->fillForm(fn (TentativeReservation $record) => ['landlord_notes' => $record->landlord_notes])
                    ->action(function (TentativeReservation $record, array $data): void {
                        $record->update(['landlord_notes' => $data['landlord_notes']]);

                        Notification::make()
                            ->title('Notes enregistrées')
                            ->success()
                            ->send();
                    }),

                Action::make('cancel')
                    ->label('Annuler')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (TentativeReservation $record) => $record->status->isActive())
                    ->slideOver()
                    ->modalHeading('Annuler la visite')
                    ->modalIconColor('danger')
                    ->form([
                        Textarea::make('cancellation_reason')
                            ->label('Motif d\'annulation (optionnel, transmis au locataire)')
                            ->rows(3)
                            ->placeholder('Ex: Bien déjà réservé, indisponibilité…'),
                    ])
                    ->action(function (TentativeReservation $record, array $data): void {
                        $record->update([
                            'status' => ReservationStatus::Cancelled,
                            'cancelled_by' => CancelledBy::Landlord,
                            'cancellation_reason' => $data['cancellation_reason'] ?? null,
                        ]);

                        Notification::make()
                            ->title('Visite annulée')
                            ->body('Le locataire a été notifié.')
                            ->warning()
                            ->send();
                    }),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateIcon('heroicon-o-calendar')
            ->emptyStateHeading('Aucune demande de visite')
            ->emptyStateDescription('Les demandes de visite pour vos annonces apparaîtront ici.');
    }

    #[\Override]
    public static function getPages(): array
    {
        return [
            'index' => ManageViewingReservations::route('/'),
        ];
    }
}
