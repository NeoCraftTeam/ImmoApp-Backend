<?php

use App\Providers\AppServiceProvider;
use App\Providers\AuthServiceProvider;
use App\Providers\EventServiceProvider;
use App\Providers\Filament\AdminPanelProvider;
use App\Providers\Filament\AgencyPanelProvider;
use App\Providers\Filament\BailleurPanelProvider;
use App\Providers\TelescopeServiceProvider;

return array_filter([
    AppServiceProvider::class,
    AuthServiceProvider::class,
    EventServiceProvider::class,
    AdminPanelProvider::class,
    AgencyPanelProvider::class,
    BailleurPanelProvider::class,
    TelescopeServiceProvider::class,
]);
