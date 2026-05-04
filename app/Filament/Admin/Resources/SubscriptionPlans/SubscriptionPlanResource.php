<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\SubscriptionPlans;

use App\Filament\Admin\Resources\SubscriptionPlans\Pages\CreateSubscriptionPlan;
use App\Filament\Admin\Resources\SubscriptionPlans\Pages\EditSubscriptionPlan;
use App\Filament\Admin\Resources\SubscriptionPlans\Pages\ListSubscriptionPlans;
use App\Filament\Admin\Resources\SubscriptionPlans\Schemas\SubscriptionPlanForm;
use App\Filament\Admin\Resources\SubscriptionPlans\Tables\SubscriptionPlansTable;
use App\Models\SubscriptionPlan;
use BackedEnum;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class SubscriptionPlanResource extends Resource
{
    protected static ?string $model = SubscriptionPlan::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static \UnitEnum|string|null $navigationGroup = 'Abonnements';

    protected static ?string $navigationLabel = 'Plans d\'abonnement';

    protected static ?string $modelLabel = 'Plan d\'abonnement';

    protected static ?string $pluralModelLabel = 'Plans d\'abonnement';

    protected static ?int $navigationSort = 1;

    #[\Override]
    public static function form(Schema $schema): Schema
    {
        return SubscriptionPlanForm::configure($schema);
    }

    #[\Override]
    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                Section::make('Plan d\'abonnement')
                    ->icon(Heroicon::OutlinedRectangleStack)
                    ->columns(2)
                    ->schema([
                        TextEntry::make('name')
                            ->label('Nom du plan')
                            ->weight('bold')
                            ->size('lg'),
                        TextEntry::make('slug')
                            ->label('Slug')
                            ->copyable()
                            ->badge()
                            ->color('gray'),
                        TextEntry::make('description')
                            ->label('Description')
                            ->placeholder('Aucune description')
                            ->columnSpanFull()
                            ->prose(),
                        TextEntry::make('price')
                            ->label('Prix mensuel')
                            ->money('XAF')
                            ->icon(Heroicon::BankNotes),
                        TextEntry::make('price_yearly')
                            ->label('Prix annuel')
                            ->money('XAF')
                            ->icon(Heroicon::BankNotes)
                            ->placeholder('Non disponible'),
                        TextEntry::make('duration_days')
                            ->label('Durée')
                            ->suffix(' jours')
                            ->icon(Heroicon::Clock),
                    ]),

                Section::make('Boost & Limites')
                    ->icon(Heroicon::Bolt)
                    ->iconColor('warning')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('boost_score')
                            ->label('Score de boost')
                            ->badge()
                            ->color('success')
                            ->formatStateUsing(fn ($state): string => "+{$state} pts"),
                        TextEntry::make('boost_duration_days')
                            ->label('Durée du boost')
                            ->suffix(' jours'),
                        TextEntry::make('max_ads')
                            ->label('Max annonces')
                            ->formatStateUsing(fn ($state): string => $state ? (string) $state : 'Illimité')
                            ->badge()
                            ->color('info'),
                    ]),

                Section::make('Paramètres')
                    ->icon(Heroicon::Cog6Tooth)
                    ->columns(2)
                    ->schema([
                        IconEntry::make('is_active')
                            ->label('Plan actif')
                            ->boolean(),
                        TextEntry::make('subscriptions_count')
                            ->label('Abonnements actifs')
                            ->badge()
                            ->color('primary')
                            ->state(fn (SubscriptionPlan $record): int => $record->subscriptions()->count()),
                        TextEntry::make('created_at')
                            ->label('Créé le')
                            ->dateTime('d/m/Y à H:i')
                            ->placeholder('—'),
                        TextEntry::make('updated_at')
                            ->label('Modifié le')
                            ->dateTime('d/m/Y à H:i')
                            ->placeholder('—'),
                    ]),
            ]);
    }

    #[\Override]
    public static function table(Table $table): Table
    {
        return SubscriptionPlansTable::configure($table);
    }

    #[\Override]
    public static function getRelations(): array
    {
        return [];
    }

    #[\Override]
    public static function getPages(): array
    {
        return [
            'index' => ListSubscriptionPlans::route('/'),
            'create' => CreateSubscriptionPlan::route('/create'),
            'edit' => EditSubscriptionPlan::route('/{record}/edit'),
        ];
    }
}
