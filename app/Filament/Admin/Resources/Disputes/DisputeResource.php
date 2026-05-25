<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Disputes;

use App\Enums\AdminPermission;
use App\Enums\DisputeStatus;
use App\Filament\Admin\Resources\Disputes\Pages\EditDispute;
use App\Filament\Admin\Resources\Disputes\Pages\ListDisputes;
use App\Filament\Admin\Resources\Disputes\Schemas\DisputeForm;
use App\Filament\Admin\Resources\Disputes\Tables\DisputesTable;
use App\Models\Dispute;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class DisputeResource extends Resource
{
    protected static ?string $model = Dispute::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedScale;

    protected static string|\UnitEnum|null $navigationGroup = 'Annonces';

    protected static ?string $navigationLabel = 'Litiges';

    protected static ?string $modelLabel = 'Litige';

    protected static ?string $pluralModelLabel = 'Litiges';

    protected static ?int $navigationSort = 4;

    #[\Override]
    public static function canViewAny(): bool
    {
        return auth()->user()?->hasAdminPermission(AdminPermission::DisputesManage) ?? false;
    }

    #[\Override]
    public static function form(Schema $schema): Schema
    {
        return DisputeForm::configure($schema);
    }

    #[\Override]
    public static function table(Table $table): Table
    {
        return DisputesTable::configure($table);
    }

    #[\Override]
    public static function canCreate(): bool
    {
        return false;
    }

    #[\Override]
    public static function getPages(): array
    {
        return [
            'index' => ListDisputes::route('/'),
            'edit' => EditDispute::route('/{record}/edit'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        $open = Dispute::query()
            ->whereIn('status', [
                DisputeStatus::OPEN->value,
                DisputeStatus::UNDER_REVIEW->value,
                DisputeStatus::MEDIATION->value,
            ])
            ->count();

        return $open > 0 ? (string) $open : null;
    }
}
