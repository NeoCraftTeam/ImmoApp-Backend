<?php

namespace App\Filament\Admin\Resources\Payments;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\PaymentType;
use App\Filament\Admin\Resources\Payments\Pages\ManagePayments;
use App\Filament\Exports\PaymentExporter;
use App\Filament\Imports\PaymentImporter;
use App\Models\Payment;
use BackedEnum;
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
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class PaymentResource extends Resource
{
    protected static ?string $model = Payment::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::CurrencyDollar;

    protected static ?string $recordTitleAttribute = 'id';

    #[\Override]
    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('type')
                    ->options(PaymentType::class)
                    ->default('unlock')
                    ->required(),
                TextInput::make('amount')
                    ->required()
                    ->numeric(),
                TextInput::make('transaction_id')
                    ->required(),
                Select::make('payment_method')
                    ->options(PaymentMethod::class)
                    ->required(),
                Select::make('ad_id')
                    ->relationship('ad', 'title')
                    ->required(),
                Select::make('user_id')
                    ->relationship('user', 'id')
                    ->required(),
                Select::make('status')
                    ->options(PaymentStatus::class)
                    ->default('pending')
                    ->required(),
            ]);
    }

    #[\Override]
    public static function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('type')
            ->columns([
                TextColumn::make('type')
                    ->badge()
                    ->searchable()
                    ->visible(false),
                TextColumn::make('amount')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('transaction_id')
                    ->searchable(),
                TextColumn::make('payment_method')
                    ->badge()
                    ->searchable(),
                TextColumn::make('ad.title')
                    ->searchable(),
                TextColumn::make('ad.ad_type.name')
                    ->searchable(),
                TextColumn::make('user.fullname')
                    ->searchable(),
                TextColumn::make('status')
                    ->badge()
                    ->searchable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('deleted_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TrashedFilter::make(),
            ])
            ->recordActions([
                EditAction::make(),
                ViewAction::make(),
                DeleteAction::make(),
                ForceDeleteAction::make(),
                RestoreAction::make(),
            ])->headerActions([
                ImportAction::make()->label('Importer')
                    ->importer(PaymentImporter::class)
                    ->icon(Heroicon::ArrowUpTray),

                ExportAction::make()->label('Exporter')
                    ->exporter(PaymentExporter::class)
                    ->icon(Heroicon::ArrowDownTray),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManagePayments::route('/'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
