<?php

declare(strict_types=1);

namespace App\Filament\Agency\Resources\Reviews;

use App\Filament\Agency\Resources\Reviews\Pages\ManageReviews;
use App\Models\Review;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class ReviewResource extends Resource
{
    protected static ?string $model = Review::class;

    protected static ?string $tenantOwnershipRelationshipName = 'agency';

    protected static string|null|UnitEnum $navigationGroup = 'Retours';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::Star;

    protected static ?string $navigationLabel = 'Avis sur mes annonces';

    protected static ?string $modelLabel = 'Avis';

    protected static ?string $pluralModelLabel = 'Avis';

    #[\Override]
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->whereHas('ad', fn ($q) => $q->where('user_id', auth()->id()))
            ->with(['user', 'ad']);
    }

    public static function getNavigationBadge(): ?string
    {
        return (string) static::getEloquentQuery()->count();
    }

    #[\Override]
    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Avis du client')
                    ->icon('heroicon-o-star')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('rating')
                            ->label('Note')
                            ->formatStateUsing(fn ($state) => str_repeat('★', (int) $state).str_repeat('☆', 5 - (int) $state)." ({$state}/5)")
                            ->color(fn ($state) => match (true) {
                                $state >= 4 => 'success',
                                $state >= 3 => 'warning',
                                default => 'danger',
                            })
                            ->weight(FontWeight::Bold),
                        TextEntry::make('created_at')
                            ->label('Posté le')
                            ->dateTime('d/m/Y à H:i')
                            ->icon('heroicon-o-calendar'),
                        TextEntry::make('comment')
                            ->label('Commentaire')
                            ->placeholder('Aucun commentaire')
                            ->columnSpanFull(),
                    ]),
                Section::make('Client')
                    ->icon('heroicon-o-user')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('user.fullname')
                            ->label('Nom')
                            ->icon('heroicon-o-user-circle'),
                        TextEntry::make('user.email')
                            ->label('Email')
                            ->icon('heroicon-o-envelope')
                            ->copyable(),
                    ]),
                Section::make('Annonce concernée')
                    ->icon('heroicon-o-home')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('ad.title')
                            ->label('Titre')
                            ->icon('heroicon-o-document-text'),
                        TextEntry::make('ad.price')
                            ->label('Prix')
                            ->money('XOF')
                            ->icon('heroicon-o-banknotes'),
                    ]),
            ]);
    }

    #[\Override]
    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->defaultGroup(
                Group::make('ad.title')
                    ->label('Annonce')
                    ->collapsible()
                    ->titlePrefixedWithLabel(false)
            )
            ->groups([
                Group::make('ad.title')
                    ->label('Par annonce')
                    ->collapsible()
                    ->titlePrefixedWithLabel(false),
                Group::make('rating')
                    ->label('Par note')
                    ->collapsible(),
            ])
            ->columns([
                TextColumn::make('rating')
                    ->label('Note')
                    ->formatStateUsing(fn ($state) => str_repeat('★', (int) $state).str_repeat('☆', 5 - (int) $state))
                    ->color(fn ($state) => match (true) {
                        $state >= 4 => 'success',
                        $state >= 3 => 'warning',
                        default => 'danger',
                    })
                    ->sortable()
                    ->alignCenter(),
                TextColumn::make('user.fullname')
                    ->label('Client')
                    ->searchable()
                    ->icon('heroicon-o-user-circle'),
                TextColumn::make('comment')
                    ->label('Commentaire')
                    ->limit(80)
                    ->wrap()
                    ->placeholder('—')
                    ->tooltip(fn ($record) => $record->comment),
                TextColumn::make('created_at')
                    ->label('Date')
                    ->since()
                    ->sortable()
                    ->tooltip(fn ($record) => $record->created_at?->format('d/m/Y à H:i')),
            ])
            ->filters([
                SelectFilter::make('rating')
                    ->label('Note')
                    ->options([
                        '5' => '★★★★★ Excellent',
                        '4' => '★★★★☆ Bien',
                        '3' => '★★★☆☆ Correct',
                        '2' => '★★☆☆☆ Passable',
                        '1' => '★☆☆☆☆ Mauvais',
                    ]),
            ])
            ->recordActions([
                ViewAction::make(),
            ])
            ->toolbarActions([])
            ->emptyStateHeading('Aucun avis pour le moment')
            ->emptyStateDescription('Les avis de vos clients apparaîtront ici.')
            ->emptyStateIcon('heroicon-o-star');
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageReviews::route('/'),
        ];
    }
}
