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
use Filament\Resources\Resource;
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
    public static function table(Table $table): Table
    {
        return SubscriptionPlansTable::configure($table);
    }

    #[\Override]
    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSubscriptionPlans::route('/'),
            'create' => CreateSubscriptionPlan::route('/create'),
            'edit' => EditSubscriptionPlan::route('/{record}/edit'),
        ];
    }
}
