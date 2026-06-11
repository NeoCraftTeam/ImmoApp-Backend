<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\TentativeReservations;

use App\Enums\AdminPermission;
use App\Enums\ReservationStatus;
use App\Filament\Admin\Resources\TentativeReservations\Pages\ManageTentativeReservations;
use App\Models\TentativeReservation;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

final class TentativeReservationResource extends Resource
{
    protected static ?string $model = TentativeReservation::class;

    protected static bool $isScopedToTenant = false;

    #[\Override]
    public static function canViewAny(): bool
    {
        return auth()->user()?->hasAdminPermission(AdminPermission::AdsView) ?? false;
    }

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::CalendarDays;

    protected static string|null|\UnitEnum $navigationGroup = 'Contrats & Réservations';

    protected static ?int $navigationSort = 2;

    protected static ?string $label = 'Réservation de visite';

    protected static ?string $pluralLabel = 'Réservations de visites';

    protected static ?string $navigationLabel = 'Réservations';

    #[\Override]
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['ad', 'client'])
            ->latest();
    }

    #[\Override]
    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    #[\Override]
    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                Section::make('Réservation')
                    ->icon(Heroicon::CalendarDays)
                    ->columns(2)
                    ->schema([
                        TextEntry::make('client.name')
                            ->label('Client')
                            ->icon(Heroicon::User),
                        TextEntry::make('client.email')
                            ->label('Email client')
                            ->copyable(),
                        TextEntry::make('ad.title')
                            ->label('Annonce')
                            ->icon(Heroicon::Home),
                        TextEntry::make('status')
                            ->label('Statut')
                            ->badge()
                            ->color(fn (ReservationStatus $state): string => match ($state) {
                                ReservationStatus::Pending => 'warning',
                                ReservationStatus::Confirmed => 'success',
                                ReservationStatus::Cancelled => 'danger',
                                ReservationStatus::Expired => 'gray',
                                ReservationStatus::Completed => 'info',
                                ReservationStatus::NoShow => 'danger',
                            })
                            ->formatStateUsing(fn (ReservationStatus $state): string => $state->label()),
                        TextEntry::make('slot_date')
                            ->label('Date de visite')
                            ->date('d/m/Y'),
                        TextEntry::make('slot_starts_at')
                            ->label('Début créneau'),
                        TextEntry::make('slot_ends_at')
                            ->label('Fin créneau'),
                        TextEntry::make('expires_at')
                            ->label('Expire le')
                            ->dateTime('d/m/Y H:i'),
                        TextEntry::make('client_message')
                            ->label('Message client')
                            ->columnSpanFull(),
                        TextEntry::make('cancellation_reason')
                            ->label("Raison d'annulation")
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    #[\Override]
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('client.name')
                    ->label('Client')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('ad.title')
                    ->label('Annonce')
                    ->searchable()
                    ->limit(40),
                TextColumn::make('slot_date')
                    ->label('Date de visite')
                    ->date('d/m/Y')
                    ->sortable(),
                TextColumn::make('slot_starts_at')
                    ->label('Créneau'),
                TextColumn::make('status')
                    ->label('Statut')
                    ->badge()
                    ->color(fn (ReservationStatus $state): string => match ($state) {
                        ReservationStatus::Pending => 'warning',
                        ReservationStatus::Confirmed => 'success',
                        ReservationStatus::Cancelled => 'danger',
                        ReservationStatus::Expired => 'gray',
                        ReservationStatus::Completed => 'info',
                        ReservationStatus::NoShow => 'danger',
                    })
                    ->formatStateUsing(fn (ReservationStatus $state): string => $state->label()),
                TextColumn::make('expires_at')
                    ->label('Expire le')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->options(ReservationStatus::class)
                    ->label('Statut'),
            ])
            ->actions([
                ViewAction::make(),
            ])
            ->bulkActions([]);
    }

    #[\Override]
    public static function getPages(): array
    {
        return [
            'index' => ManageTentativeReservations::route('/'),
        ];
    }
}
