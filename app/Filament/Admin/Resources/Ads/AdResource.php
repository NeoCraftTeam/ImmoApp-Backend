<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Ads;

use App\Enums\AdStatus;
use App\Filament\Admin\Resources\Ads\Pages\CreateAd;
use App\Filament\Admin\Resources\Ads\Pages\EditAd;
use App\Filament\Admin\Resources\Ads\Pages\ListAds;
use App\Filament\Admin\Resources\Ads\Pages\ViewAd;
use App\Filament\Admin\Resources\Ads\RelationManagers\PaymentsRelationManager;
use App\Filament\Exports\AdExporter;
use App\Filament\Imports\AdImporter;
use App\Filament\Resources\Ads\Concerns\SharedAdResource;
use App\Mail\AdDeclinedMail;
use App\Models\Ad;
use BackedEnum;
use Carbon\Carbon;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ExportAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\ImportAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use UnitEnum;

class AdResource extends Resource
{
    use SharedAdResource;

    protected static ?string $model = Ad::class;

    protected static string|null|UnitEnum $navigationGroup = 'Annonces';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::Megaphone;

    protected static ?string $recordTitleAttribute = 'title';

    protected static ?string $navigationLabel = 'Toutes les annonces';

    protected static ?string $modelLabel = 'Annonce';

    protected static ?int $navigationSort = 1;

    #[\Override]
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['user.agency', 'quarter', 'ad_type', 'media']);
    }

    #[\Override]
    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                ...static::getSharedFormFields(),
                static::getStatusSelect(isAdmin: true),
                static::getTourSection(),
                Select::make('user_id')
                    ->label('Propriétaire')
                    ->relationship('user', 'firstname')
                    ->getOptionLabelFromRecordUsing(fn ($record) => $record->fullname)
                    ->searchable(['firstname', 'lastname'])
                    ->preload()
                    ->required(),
                ...static::getRelationSelects(),
            ]);
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
            ->columns(static::getSharedTableColumns(isAdmin: true))
            ->filters([
                TrashedFilter::make(),
                SelectFilter::make('status')
                    ->options(AdStatus::class)
                    ->label('Statut'),
                SelectFilter::make('type_id')
                    ->label('Type de bien')
                    ->relationship('ad_type', 'name')
                    ->searchable()
                    ->preload()
                    ->native(false),
                SelectFilter::make('quarter')
                    ->label('Quartier')
                    ->relationship('quarter', 'name')
                    ->searchable()
                    ->preload()
                    ->native(false),
                Filter::make('price_range')
                    ->label('Fourchette de prix')
                    ->form([
                        TextInput::make('price_from')
                            ->label('Prix min')
                            ->numeric()
                            ->prefix('XAF'),
                        TextInput::make('price_to')
                            ->label('Prix max')
                            ->numeric()
                            ->prefix('XAF'),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['price_from'], fn (Builder $q, $price) => $q->where('price', '>=', $price))
                        ->when($data['price_to'], fn (Builder $q, $price) => $q->where('price', '<=', $price))),
                Filter::make('created_at')
                    ->label('Date de publication')
                    ->form([
                        DatePicker::make('created_from')
                            ->label('Du')
                            ->native(false),
                        DatePicker::make('created_until')
                            ->label('Au')
                            ->native(false),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['created_from'], fn (Builder $q, $date) => $q->whereDate('created_at', '>=', $date))
                        ->when($data['created_until'], fn (Builder $q, $date) => $q->whereDate('created_at', '<=', $date)))
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];
                        if ($data['created_from'] ?? null) {
                            $indicators[] = 'Depuis le '.Carbon::parse($data['created_from'])->format('d/m/Y');
                        }
                        if ($data['created_until'] ?? null) {
                            $indicators[] = 'Avant le '.Carbon::parse($data['created_until'])->format('d/m/Y');
                        }

                        return $indicators;
                    }),
                Filter::make('has_tour')
                    ->label('Avec visite 3D')
                    ->toggle()
                    ->query(fn (Builder $query) => $query->where('has_3d_tour', true)),
            ])
            ->recordActions([
                ViewAction::make()
                    ->slideOver()
                    ->modalIcon('heroicon-o-megaphone')
                    ->modalIconColor('primary')
                    ->modalHeading(fn (Ad $record): string => $record->title)
                    ->modalWidth('4xl'),
                EditAction::make()
                    ->successNotificationTitle('Annonce mise à jour')
                    ->mutateFormDataUsing(fn (array $data): array => static::mutateLocationMapData($data)),
                DeleteAction::make()
                    ->successNotificationTitle('Annonce supprimée'),
                ForceDeleteAction::make()
                    ->successNotificationTitle('Annonce supprimée définitivement'),
                RestoreAction::make()
                    ->successNotificationTitle('Annonce restaurée'),
            ])->headerActions([
                ImportAction::make()->label('Importer')
                    ->importer(AdImporter::class)
                    ->icon(Heroicon::ArrowUpTray),

                ExportAction::make()->label('Exporter')
                    ->exporter(AdExporter::class)
                    ->icon(Heroicon::ArrowDownTray),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('approve')
                        ->label('Approuver')
                        ->icon(Heroicon::CheckCircle)
                        ->color('success')
                        ->requiresConfirmation()
                        ->deselectRecordsAfterCompletion()
                        ->action(function (Collection $records): void {
                            /** @var Collection<int, Ad> $records */
                            $records->load('user');
                            foreach ($records as $ad) {
                                if ($ad->status !== AdStatus::PENDING) {
                                    continue;
                                }
                                $ad->forceFill(['status' => AdStatus::AVAILABLE])->save();
                            }
                        }),
                    BulkAction::make('reject')
                        ->label('Rejeter')
                        ->icon(Heroicon::XCircle)
                        ->color('danger')
                        ->requiresConfirmation()
                        ->deselectRecordsAfterCompletion()
                        ->action(function (Collection $records): void {
                            /** @var Collection<int, Ad> $records */
                            $records->load('user');
                            foreach ($records as $ad) {
                                if ($ad->user) {
                                    try {
                                        Mail::to($ad->user)->send(new AdDeclinedMail($ad, 'Annonce refusée par l\'administration.'));
                                    } catch (\Throwable $e) {
                                        Log::error('Bulk reject email failed: '.$e->getMessage());
                                    }
                                }
                            }
                            Ad::query()->whereKey($records->modelKeys())->update([
                                'status' => AdStatus::DECLINED->value,
                            ]);
                        }),
                    BulkAction::make('archive')
                        ->label('Archiver')
                        ->icon(Heroicon::ArchiveBox)
                        ->color('warning')
                        ->requiresConfirmation()
                        ->deselectRecordsAfterCompletion()
                        ->action(function (Collection $records): void {
                            Ad::query()->whereKey($records->modelKeys())->delete();
                        }),
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }

    #[\Override]
    public static function getRelations(): array
    {
        return [
            PaymentsRelationManager::class,
        ];
    }

    #[\Override]
    public static function getPages(): array
    {
        return [
            'index' => ListAds::route('/'),
            'create' => CreateAd::route('/create'),
            'view' => ViewAd::route('/{record}'),
            'edit' => EditAd::route('/{record}/edit'),
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
