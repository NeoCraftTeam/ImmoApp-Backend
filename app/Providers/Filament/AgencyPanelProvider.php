<?php

namespace App\Providers\Filament;

use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets\AccountWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AgencyPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('agency')
            ->path('agency')
            ->login()
            ->passwordReset()
            ->registration(\App\Filament\Pages\Auth\CustomRegister::class)
            ->profile()
            ->emailVerification()
            ->colors([
                'primary' => Color::Blue,
            ])
            ->tenant(\App\Models\Agency::class)
            ->discoverResources(in: app_path('Filament/Agency/Resources'), for: 'App\Filament\Agency\Resources')
            ->discoverPages(in: app_path('Filament/Agency/Pages'), for: 'App\Filament\Agency\Pages')
            ->pages([
                // Dashboard::class est retiré car nous avons un Dashboard personnalisé découvert automatiquement
            ])
            ->discoverWidgets(in: app_path('Filament/Agency/Widgets'), for: 'App\Filament\Agency\Widgets')
            ->widgets([
                AccountWidget::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ])
            ->renderHook(
                'panels::body.start',
                fn (): string => '<script>if(window.location.search.includes("app_mode=native") || window.ReactNativeWebView) { document.body.classList.add("is-mobile-app"); }</script>',
            )
            ->renderHook(
                'panels::body.end',
                fn () => view('filament.mobile-bridge'),
            )
            ->assets([
                \Filament\Support\Assets\Css::make('filament-mobile-app', resource_path('css/filament-mobile-app.css')),
            ]);
    }
}
