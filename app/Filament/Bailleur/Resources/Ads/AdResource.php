<?php

declare(strict_types=1);

namespace App\Filament\Bailleur\Resources\Ads;

use App\Enums\AdStatus;
use App\Filament\Bailleur\Resources\Ads\Pages\ManageAds;
use App\Filament\Resources\Ads\Concerns\SharedAdResource;
use App\Models\Ad;
use App\Models\Scopes\LandlordScope;
use App\Services\LeaseContractService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Storage;
use UnitEnum;

class AdResource extends Resource
{
    use SharedAdResource;

    protected static ?string $model = Ad::class;

    protected static string|null|UnitEnum $navigationGroup = 'Mes Biens';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::Home;

    protected static ?string $recordTitleAttribute = 'title';

    protected static ?string $navigationLabel = 'Mes Annonces';

    protected static ?string $modelLabel = 'Annonce';

    protected static ?string $pluralModelLabel = 'Annonces';

    protected static ?int $navigationSort = 1;

    #[\Override]
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withGlobalScope('landlord', new LandlordScope);
    }

    #[\Override]
    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                ...static::getSharedFormFields(),
                static::getOwnerStatusSection(),
                static::getTourSection(),
                ...static::getRelationSelects(),
            ]);
    }

    #[\Override]
    public static function infolist(Schema $schema): Schema
    {
        return $schema->components(static::getSharedInfolistSchema());
    }

    #[\Override]
    public static function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('title')
            ->columns(static::getSharedTableColumns())
            ->filters([
                TrashedFilter::make(),
            ])
            ->actions([
                ViewAction::make(),
                EditAction::make()
                    ->slideOver()
                    ->stickyModalHeader()
                    ->stickyModalFooter()
                    ->modalAutofocus(false)
                    ->closeModalByClickingAway(false)
                    ->modalWidth(Width::FourExtraLarge)
                    ->successNotificationTitle('Annonce mise à jour')
                    ->mutateFormDataUsing(fn (array $data): array => static::mutateLocationMapData($data)),
                Action::make('generateContract')
                    ->label('Contrat de bail')
                    ->icon(Heroicon::DocumentText)
                    ->color('info')
                    ->tooltip('Générer un contrat de bail PDF pré-rempli')
                    ->visible(fn (Ad $record): bool => in_array($record->status, [AdStatus::AVAILABLE, AdStatus::RESERVED]))
                    ->form([
                        TextInput::make('unit_reference')
                            ->label('Référence du logement')
                            ->placeholder('Ex: Chambre 12, Local B3, Appartement 2A...')
                            ->helperText('Numéro de chambre, de local ou toute référence utile pour identifier le logement'),
                        TextInput::make('tenant_name')
                            ->label('Nom complet du locataire')
                            ->required(),
                        TextInput::make('tenant_phone')
                            ->label('Téléphone du locataire')
                            ->tel()
                            ->required(),
                        TextInput::make('tenant_email')
                            ->label('Email du locataire')
                            ->email(),
                        TextInput::make('tenant_id_number')
                            ->label('N° CNI / Passeport'),
                        DatePicker::make('lease_start')
                            ->label('Date de début du bail')
                            ->default(now())
                            ->required(),
                        Select::make('lease_duration_months')
                            ->label('Durée du bail')
                            ->options([
                                6 => '6 mois',
                                12 => '12 mois (1 an)',
                                24 => '24 mois (2 ans)',
                                36 => '36 mois (3 ans)',
                            ])
                            ->default(12)
                            ->required(),
                        Textarea::make('special_conditions')
                            ->label('Conditions particulières (optionnel)')
                            ->rows(3),
                    ])
                    ->modalHeading('Générer un contrat de bail')
                    ->modalDescription('Les informations de votre annonce seront automatiquement pré-remplies.')
                    ->modalSubmitActionLabel('Générer le PDF')
                    ->modalWidth(Width::TwoExtraLarge)
                    ->action(function (Ad $record, array $data): \Symfony\Component\HttpFoundation\StreamedResponse {
                        $record->load(['ad_type', 'quarter.city']);

                        $contract = app(LeaseContractService::class)->generate(
                            $record,
                            auth()->user(),
                            $data,
                        );

                        Notification::make()
                            ->title('Contrat généré')
                            ->body("Contrat {$contract->contract_number} sauvegardé. Retrouvez-le dans Mes Contrats.")
                            ->success()
                            ->send();

                        return response()->streamDownload(
                            fn () => print (Storage::disk('public')->get($contract->pdf_path)),
                            'contrat-bail-'.str($record->title)->slug().'-'.now()->format('Ymd').'.pdf',
                            ['Content-Type' => 'application/pdf'],
                        );
                    }),
                Action::make('resubmit')
                    ->label('Soumettre à nouveau')
                    ->icon(Heroicon::ArrowPath)
                    ->color('warning')
                    ->tooltip('Corriger et resoumettre cette annonce pour validation')
                    ->visible(fn (Ad $record): bool => $record->status === AdStatus::DECLINED)
                    ->requiresConfirmation()
                    ->modalHeading('Soumettre à nouveau')
                    ->modalDescription('Votre annonce sera envoyée à l\'administrateur pour une nouvelle vérification.')
                    ->action(function (Ad $record): void {
                        $record->forceFill(['status' => AdStatus::PENDING])->save();

                        Notification::make()
                            ->title('Annonce soumise à nouveau')
                            ->body('Votre annonce est en attente de validation par notre équipe.')
                            ->success()
                            ->send();
                    }),
                DeleteAction::make()
                    ->successNotificationTitle('Annonce supprimée'),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    #[\Override]
    public static function getPages(): array
    {
        return [
            'index' => ManageAds::route('/'),
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

    public static function getNavigationBadge(): ?string
    {
        return (string) static::getModel()::query()
            ->withGlobalScope('landlord', new LandlordScope)
            ->count();
    }

    #[\Override]
    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Nombre d\'annonces';
    }
}
