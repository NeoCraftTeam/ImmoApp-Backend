<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\NewsletterSubscribers;

use App\Filament\Admin\Resources\NewsletterSubscribers\Pages\ManageNewsletterSubscribers;
use App\Models\NewsletterSubscriber;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

final class NewsletterSubscriberResource extends Resource
{
    protected static ?string $model = NewsletterSubscriber::class;

    protected static string|null|\UnitEnum $navigationGroup = 'Marketing';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::Envelope;

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Abonnés Newsletter';

    protected static ?string $modelLabel = 'Abonné';

    protected static ?string $pluralModelLabel = 'Abonnés Newsletter';

    protected static ?string $recordTitleAttribute = 'email';

    protected static bool $isScopedToTenant = false;

    #[\Override]
    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Informations')
                    ->icon(Heroicon::User)
                    ->columns(2)
                    ->schema([
                        TextInput::make('email')
                            ->label('Email')
                            ->email()
                            ->required()
                            ->maxLength(255)
                            ->prefixIcon(Heroicon::Envelope),
                        TextInput::make('name')
                            ->label('Nom')
                            ->maxLength(100)
                            ->prefixIcon(Heroicon::User),
                        Select::make('locale')
                            ->label('Langue')
                            ->options([
                                'fr' => 'Français',
                                'en' => 'English',
                            ])
                            ->default('fr')
                            ->native(false),
                        TextInput::make('source')
                            ->label('Source')
                            ->maxLength(50)
                            ->default('website'),
                    ]),

                Section::make('Statut')
                    ->icon(Heroicon::CheckCircle)
                    ->columns(2)
                    ->schema([
                        DateTimePicker::make('confirmed_at')
                            ->label('Confirmé le')
                            ->native(false),
                        DateTimePicker::make('unsubscribed_at')
                            ->label('Désabonné le')
                            ->native(false),
                    ]),
            ]);
    }

    #[\Override]
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('email')
                    ->label('Email')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->copyMessage('Email copié'),
                TextColumn::make('name')
                    ->label('Nom')
                    ->searchable()
                    ->sortable()
                    ->placeholder('—'),
                TextColumn::make('locale')
                    ->label('Langue')
                    ->badge()
                    ->sortable(),
                TextColumn::make('source')
                    ->label('Source')
                    ->badge()
                    ->color('gray')
                    ->sortable(),
                IconColumn::make('is_active')
                    ->label('Actif')
                    ->state(fn (NewsletterSubscriber $record): bool => $record->isSubscribed())
                    ->boolean()
                    ->sortable(query: fn (Builder $query, string $direction): Builder => $query->orderByRaw(
                        'CASE WHEN confirmed_at IS NOT NULL AND unsubscribed_at IS NULL THEN 1 ELSE 0 END '.$direction
                    )),
                TextColumn::make('confirmed_at')
                    ->label('Confirmé le')
                    ->dateTime('d/m/Y à H:i')
                    ->sortable()
                    ->placeholder('Non confirmé'),
                TextColumn::make('unsubscribed_at')
                    ->label('Désabonné le')
                    ->dateTime('d/m/Y à H:i')
                    ->sortable()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->label('Inscrit le')
                    ->dateTime('d/m/Y à H:i')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->striped()
            ->filters([
                TernaryFilter::make('is_active')
                    ->label('Actif')
                    ->queries(
                        true: fn (Builder $query) => $query->whereNotNull('confirmed_at')->whereNull('unsubscribed_at'),
                        false: fn (Builder $query) => $query->where(function (Builder $q): void {
                            $q->whereNull('confirmed_at')->orWhereNotNull('unsubscribed_at');
                        }),
                    ),
                SelectFilter::make('locale')
                    ->label('Langue')
                    ->options([
                        'fr' => 'Français',
                        'en' => 'English',
                    ])
                    ->native(false),
                SelectFilter::make('source')
                    ->label('Source')
                    ->options(fn () => NewsletterSubscriber::query()
                        ->distinct()
                        ->pluck('source', 'source')
                        ->toArray())
                    ->native(false),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make()
                    ->successNotificationTitle('Abonné mis à jour'),
                DeleteAction::make()
                    ->successNotificationTitle('Abonné supprimé'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->successNotificationTitle('Abonnés supprimés'),
                ]),
            ]);
    }

    #[\Override]
    public static function getPages(): array
    {
        return [
            'index' => ManageNewsletterSubscribers::route('/'),
        ];
    }
}
