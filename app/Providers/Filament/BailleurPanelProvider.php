<?php

declare(strict_types=1);

namespace App\Providers\Filament;

use DutchCodingCompany\FilamentSocialite\FilamentSocialitePlugin;
use DutchCodingCompany\FilamentSocialite\Provider;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class BailleurPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('bailleur')
            ->path('owner')
            ->brandLogo(fn () => view('filament.bailleur.brand'))
            ->brandLogoHeight('3.5rem')
            ->login()
            ->passwordReset()
            ->registration(\App\Filament\Pages\Auth\CustomRegister::class)
            ->profile(\App\Filament\Pages\Auth\EditProfile::class)
            ->emailVerification()
            ->colors([
                'primary' => \Filament\Support\Colors\Color::hex('#10b981'), // Vert Owner
            ])
            ->multiFactorAuthentication([
                \Filament\Auth\MultiFactor\App\AppAuthentication::make()
                    ->recoverable()
                    ->recoveryCodeCount(10)
                    ->regenerableRecoveryCodes(false)
                    ->brandName('KeyHome Owner App'),
                \Filament\Auth\MultiFactor\Email\EmailAuthentication::make(),
            ], isRequired: false)
            ->discoverResources(in: app_path('Filament/Bailleur/Resources'), for: 'App\Filament\Bailleur\Resources')
            ->discoverPages(in: app_path('Filament/Bailleur/Pages'), for: 'App\Filament\Bailleur\Pages')
            ->pages([
                // Dashboard::class
            ])
            ->discoverWidgets(in: app_path('Filament/Bailleur/Widgets'), for: 'App\Filament\Bailleur\Widgets')
            ->widgets([
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
                'panels::body.end',
                fn () => view('filament.mobile-bridge'),
            )
            ->assets([
                \Filament\Support\Assets\Css::make('filament-mobile-app', resource_path('css/filament-mobile-app.css')),
                \Filament\Support\Assets\Css::make('native-ui', resource_path('css/native-ui.css')),
                \Filament\Support\Assets\Js::make('filament-mobile-detect', resource_path('js/filament-mobile-detect.js')),
                \Filament\Support\Assets\Js::make('filament-native-bridge', resource_path('js/filament-native-bridge.js')),
                \Filament\Support\Assets\Js::make('session-persistence', resource_path('js/session-persistence.js')),
                \Filament\Support\Assets\Js::make('animations-loader', resource_path('js/animations-loader.js')),
                \Filament\Support\Assets\Js::make('connectivity-manager', resource_path('js/connectivity-manager.js')),
                \Filament\Support\Assets\Js::make('webview-performance', resource_path('js/webview-performance.js')),
            ])
            ->renderHook(
                'panels::body.end',
                fn () => view('filament.native-init'),
            )
            ->plugins([
                FilamentSocialitePlugin::make()
                    ->providers([
                        Provider::make('google')
                            ->label('Google')
                            ->icon('fab-google')
                            ->color(Color::Rose)
                            ->outlined(false)
                            ->stateless(false),
                    ])
                    ->registration(true)
                    ->rememberLogin(true)
                    ->showDivider(true),
            ]);
    }
}
