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
            ->globalSearch(false)
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
                'panels::head.end',
                fn () => view('pwa.head-meta', ['themeColor' => '#10b981']),
            )
            ->renderHook(
                'panels::head.end',
                fn () => new \Illuminate\Support\HtmlString('
                    <!-- Dynamic Island / Notch — viewport-fit=cover requis pour env(safe-area-inset-top) -->
                    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
                    <style>
                        /* Repousse la navbar Filament sous la Dynamic Island / Notch */
                        .fi-topbar {
                            padding-top: env(safe-area-inset-top) !important;
                        }
                        /* Sidebar : header aligné avec la topbar */
                        .fi-sidebar-header {
                            padding-top: calc(env(safe-area-inset-top) + 1rem) !important;
                        }
                        /* Espace en bas pour la home bar iOS */
                        body {
                            padding-bottom: env(safe-area-inset-bottom);
                        }
                    </style>
                '),
            )
            ->renderHook(
                'panels::body.end',
                fn () => view('pwa.splash'),
            )
            ->renderHook(
                'panels::body.end',
                fn () => view('filament.mobile-bridge'),
            )
            ->renderHook(
                'panels::scripts.after',
                fn () => view('pwa.register-sw'),
            )
            ->assets([
                // Bridge natif minimal — CSS et JS uniquement pour l'app mobile
                \Filament\Support\Assets\Js::make('filament-native-bridge', resource_path('js/filament-native-bridge.js')),
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
