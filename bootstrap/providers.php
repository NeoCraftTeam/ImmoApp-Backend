<?php

return array_filter([
    App\Providers\AppServiceProvider::class,
    App\Providers\AuthServiceProvider::class,
    App\Providers\EventServiceProvider::class,
    App\Providers\Filament\AdminPanelProvider::class,
    App\Providers\Filament\AgencyPanelProvider::class,
    App\Providers\Filament\BailleurPanelProvider::class,
    // SEC-009: Telescope in require-dev — only loaded when installed (local/staging)
    class_exists(\Laravel\Telescope\TelescopeApplicationServiceProvider::class)
        ? App\Providers\TelescopeServiceProvider::class
        : null,
]);
