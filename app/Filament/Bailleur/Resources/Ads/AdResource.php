<?php

declare(strict_types=1);

namespace App\Filament\Bailleur\Resources\Ads;

use App\Enums\AdStatus;
use App\Filament\Bailleur\Resources\Ads\Pages\ManageAds;
use App\Filament\Resources\Ads\Concerns\SharedAdResource;
use App\Models\Ad;
use App\Models\Scopes\LandlordScope;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
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
