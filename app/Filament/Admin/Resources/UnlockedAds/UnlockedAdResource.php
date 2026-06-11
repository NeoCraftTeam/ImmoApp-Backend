<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\UnlockedAds;

use App\Enums\AdminPermission;
use App\Filament\Admin\Resources\UnlockedAds\Pages\ManageUnlockedAds;
use App\Filament\Exports\UnlockedAdExporter;
use App\Filament\Imports\UnlockedAdImporter;
use App\Models\UnlockedAd;
use BackedEnum;
use Filament\Actions\ExportAction;
use Filament\Actions\ImportAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class UnlockedAdResource extends Resource
{
    protected static ?string $model = UnlockedAd::class;

    protected static bool $isScopedToTenant = false;

    #[\Override]
    public static function canViewAny(): bool
    {
        return auth()->user()?->hasAdminPermission(AdminPermission::PaymentsView) ?? false;
    }

    protected static string|null|\UnitEnum $navigationGroup = 'Finances';

    protected static ?int $navigationSort = 2;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::LockOpen;

    protected static ?string $recordTitleAttribute = 'id';

    protected static ?string $navigationLabel = 'Déblocages (Opérations)';

    protected static ?string $modelLabel = 'Annonce débloquée';

    protected static ?string $pluralModelLabel = 'Annonces débloquées';

    #[\Override]
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['ad.user.agency', 'user.agency', 'payment']);
    }

    #[\Override]
    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('ad_id')
                    ->label('Annonce')
                    ->relationship('ad', 'title')
                    ->required(),
                Select::make('user_id')
                    ->label('Utilisateur')
                    ->relationship('user', 'firstname')
                    ->getOptionLabelFromRecordUsing(fn ($record) => "{$record->firstname} {$record->lastname}")
                    ->searchable()
                    ->preload()
                    ->required(),
                Select::make('payment_id')
                    ->label('Paiement')
                    ->relationship('payment', 'transaction_id')
                    ->searchable()
                    ->required(),
                DateTimePicker::make('unlocked_at')
                    ->label('Débloqué le'),
            ]);
    }

    #[\Override]
    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                // ── Opération de déblocage ──────────────────────────────────────
                Section::make('Déblocage')
                    ->icon(Heroicon::LockOpen)
                    ->iconColor('success')
                    ->description('Détails de l\'opération de déblocage d\'annonce')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('unlocked_at')
                            ->label('Débloqué le')
                            ->icon(Heroicon::CalendarDays)
                            ->iconColor('success')
                            ->dateTime('d/m/Y à H:i')
                            ->badge()
                            ->color('success'),
                        TextEntry::make('payment.transaction_id')
                            ->label('Référence paiement')
                            ->icon(Heroicon::QrCode)
                            ->copyable()
                            ->copyMessage('Référence copiée !')
                            ->badge()
                            ->color('gray'),
                        TextEntry::make('ad.title')
                            ->label('Annonce débloquée')
                            ->icon(Heroicon::Megaphone)
                            ->iconColor('info')
                            ->columnSpanFull(),
                    ]),

                // ── Parties impliquées ─────────────────────────────────────────
                Section::make('Parties impliquées')
                    ->icon(Heroicon::Users)
                    ->iconColor('primary')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('user.fullname')
                            ->label('Débloquée par (locataire)')
                            ->icon(Heroicon::UserCircle)
                            ->iconColor('primary'),
                        TextEntry::make('ad.user.fullname')
                            ->label('Propriétaire de l\'annonce')
                            ->icon(Heroicon::HomeModern)
                            ->iconColor('warning'),
                        TextEntry::make('deleted_at')
                            ->label('Supprimé le')
                            ->icon(Heroicon::Trash)
                            ->iconColor('danger')
                            ->dateTime('d/m/Y à H:i')
                            ->visible(fn (UnlockedAd $record): bool => $record->trashed()),
                    ]),
            ]);
    }

    #[\Override]
    public static function table(Table $table): Table
    {
        return $table
            ->heading('Annonces débloquées')
            ->description('Historique des déblocages d\'annonces')
            ->striped()
            ->recordTitleAttribute('id')
            ->columns([
                TextColumn::make('ad.user.fullname')->label('Propriétaire')
                    ->searchable(),
                TextColumn::make('ad.title')->label('Annonce')
                    ->searchable(),
                TextColumn::make('user.fullname')->label('Débloquée par')
                    ->searchable(),
                TextColumn::make('payment.transaction_id')->label('ID Paiement')
                    ->searchable(),
                TextColumn::make('unlocked_at')
                    ->label('Débloqué le')
                    ->dateTime('d/m/Y à H:i')
                    ->sortable(),
                TextColumn::make('deleted_at')
                    ->label('Supprimé le')
                    ->dateTime('d/m/Y à H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TrashedFilter::make(),
            ])
            ->recordActions([
                ViewAction::make()
                    ->slideOver()
                    ->modalIcon('heroicon-o-lock-open')
                    ->modalIconColor('success')
                    ->modalHeading(fn (UnlockedAd $record): string => 'Déblocage — '.($record->ad->title ?? 'Annonce')
                    )
                    ->modalWidth('2xl'),
            ])
            ->headerActions([

                ImportAction::make()->label('Importer')
                    ->importer(UnlockedAdImporter::class)
                    ->icon(Heroicon::ArrowUpTray),

                ExportAction::make()->label('Exporter')
                    ->exporter(UnlockedAdExporter::class)
                    ->icon(Heroicon::ArrowDownTray),
            ])
            ->toolbarActions([
                // Immutable records
            ]);
    }

    #[\Override]
    public static function getPages(): array
    {
        return [
            'index' => ManageUnlockedAds::route('/'),
        ];
    }

    #[\Override]
    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
