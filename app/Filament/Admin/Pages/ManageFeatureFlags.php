<?php

declare(strict_types=1);

namespace App\Filament\Admin\Pages;

use App\Services\FeatureFlagService;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

class ManageFeatureFlags extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedFlag;

    protected static ?string $navigationLabel = 'Feature Flags';

    protected static string|null|UnitEnum $navigationGroup = 'System';

    protected static ?int $navigationSort = 50;

    protected string $view = 'filament.admin.pages.manage-feature-flags';

    /**
     * @var array<string, bool>
     */
    public array $flags = [];

    public function mount(): void
    {
        $this->loadFlags();
    }

    public function loadFlags(): void
    {
        $this->flags = app(FeatureFlagService::class)->all();
    }

    public function toggle(string $feature): void
    {
        $service = app(FeatureFlagService::class);

        if ($service->isEnabled($feature)) {
            $service->disable($feature);
        } else {
            $service->enable($feature);
        }

        $this->loadFlags();

        Notification::make()
            ->title("Feature '{$feature}' ".($this->flags[$feature] ? 'enabled' : 'disabled'))
            ->success()
            ->send();
    }

    public function resetFlag(string $feature): void
    {
        app(FeatureFlagService::class)->reset($feature);
        $this->loadFlags();

        Notification::make()
            ->title("Feature '{$feature}' reset to config default")
            ->info()
            ->send();
    }

    /**
     * Human-readable label for a feature flag key.
     */
    public function formatLabel(string $key): string
    {
        return str($key)->replace('_', ' ')->title()->toString();
    }
}
