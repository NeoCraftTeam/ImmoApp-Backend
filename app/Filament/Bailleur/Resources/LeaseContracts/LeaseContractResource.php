<?php

declare(strict_types=1);

namespace App\Filament\Bailleur\Resources\LeaseContracts;

use App\Filament\Bailleur\Resources\LeaseContracts\Pages\ManageLeaseContracts;
use App\Models\LeaseContract;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;
use UnitEnum;

class LeaseContractResource extends Resource
{
    protected static ?string $model = LeaseContract::class;

    protected static string|null|UnitEnum $navigationGroup = 'Mes Biens';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::DocumentText;

    protected static ?string $navigationLabel = ' Contrats de bail';

    protected static ?string $modelLabel = 'Contrat';

    protected static ?string $pluralModelLabel = 'Contrats';

    protected static ?int $navigationSort = 2;

    #[\Override]
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('user_id', auth()->id())
            ->with(['ad']);
    }

    #[\Override]
    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Contrat')
                    ->icon('heroicon-o-document-text')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('contract_number')
                            ->label('Référence')
                            ->copyable()
                            ->copyMessage('Référence copiée !'),
                        TextEntry::make('ad.title')
                            ->label('Annonce'),
                        TextEntry::make('unit_reference')
                            ->label('Référence logement')
                            ->placeholder('—'),
                        TextEntry::make('created_at')
                            ->label('Généré le')
                            ->dateTime('d/m/Y à H:i'),
                    ]),
                Section::make('Locataire')
                    ->icon('heroicon-o-user')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('tenant_name')
                            ->label('Nom'),
                        TextEntry::make('tenant_phone')
                            ->label('Téléphone'),
                        TextEntry::make('tenant_email')
                            ->label('Email')
                            ->placeholder('—'),
                        TextEntry::make('tenant_id_number')
                            ->label('N° CNI / Passeport')
                            ->placeholder('—'),
                    ]),
                Section::make('Conditions du bail')
                    ->icon('heroicon-o-banknotes')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('lease_start')
                            ->label('Début')
                            ->date('d/m/Y'),
                        TextEntry::make('lease_end')
                            ->label('Fin')
                            ->date('d/m/Y'),
                        TextEntry::make('lease_duration_months')
                            ->label('Durée')
                            ->suffix(' mois'),
                        TextEntry::make('monthly_rent')
                            ->label('Loyer mensuel')
                            ->money('XAF'),
                        TextEntry::make('deposit_amount')
                            ->label('Caution')
                            ->money('XAF')
                            ->placeholder('—'),
                        TextEntry::make('special_conditions')
                            ->label('Conditions particulières')
                            ->placeholder('Aucune')
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    #[\Override]
    public static function table(Table $table): Table
    {
        return $table
            ->heading('Mes contrats de bail')
            ->description('Retrouvez tous vos contrats générés')
            ->striped()
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('contract_number')
                    ->label('Référence')
                    ->searchable()
                    ->copyable()
                    ->copyMessage('Référence copiée !'),
                TextColumn::make('ad.title')
                    ->label('Annonce')
                    ->limit(30)
                    ->searchable()
                    ->tooltip(fn (LeaseContract $record): string => $record->ad->title ?? ''),
                TextColumn::make('tenant_name')
                    ->label('Locataire')
                    ->searchable(),
                TextColumn::make('monthly_rent')
                    ->label('Loyer')
                    ->money('XAF')
                    ->sortable(),
                TextColumn::make('lease_start')
                    ->label('Début')
                    ->date('d/m/Y')
                    ->sortable(),
                TextColumn::make('lease_end')
                    ->label('Fin')
                    ->date('d/m/Y')
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('Créé le')
                    ->dateTime('d/m/Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->recordActions([
                ViewAction::make(),
                Action::make('download')
                    ->label('Télécharger')
                    ->icon(Heroicon::ArrowDownTray)
                    ->color('info')
                    ->action(fn (LeaseContract $record): \Symfony\Component\HttpFoundation\StreamedResponse => response()->streamDownload(
                        fn () => print (Storage::disk('public')->get($record->pdf_path)),
                        "contrat-{$record->contract_number}.pdf",
                        ['Content-Type' => 'application/pdf'],
                    )),
            ])
            ->emptyStateHeading('Aucun contrat')
            ->emptyStateDescription('Générez un contrat depuis l\'une de vos annonces.')
            ->emptyStateIcon('heroicon-o-document-text');
    }

    #[\Override]
    public static function getPages(): array
    {
        return [
            'index' => ManageLeaseContracts::route('/'),
        ];
    }

    #[\Override]
    public static function canCreate(): bool
    {
        return false;
    }

    public static function getNavigationBadge(): ?string
    {
        $count = static::getEloquentQuery()->count();

        return $count > 0 ? (string) $count : null;
    }

    #[\Override]
    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Contrats générés';
    }
}
