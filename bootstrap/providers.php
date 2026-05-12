<?php

use App\Providers\AppServiceProvider;
use App\Providers\AuthServiceProvider;
use App\Providers\BroadcastServiceProvider;
use App\Providers\EventServiceProvider;
use App\Providers\Filament\AdminPanelProvider;
use App\Providers\Filament\AgencyPanelProvider;
use App\Providers\MailHeaderServiceProvider;
use App\Providers\TelescopeServiceProvider;

return array_filter([
    AppServiceProvider::class,
    AuthServiceProvider::class,
    BroadcastServiceProvider::class,
    EventServiceProvider::class,
    MailHeaderServiceProvider::class,
    AdminPanelProvider::class,
    AgencyPanelProvider::class,
    TelescopeServiceProvider::class,
]);
