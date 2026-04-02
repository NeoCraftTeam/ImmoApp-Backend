<?php

declare(strict_types=1);

namespace App\Providers\Filament;

use App\Filament\Pages\Auth\CustomRegister;
use App\Filament\Pages\Auth\EditProfile;
use App\Http\Middleware\FilamentAuthenticate;
use App\Models\Agency;
use DutchCodingCompany\FilamentSocialite\FilamentSocialitePlugin;
use DutchCodingCompany\FilamentSocialite\Provider;
use Filament\Auth\MultiFactor\App\AppAuthentication;
use Filament\Auth\MultiFactor\Email\EmailAuthentication;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Assets\Js;
use Filament\Support\Colors\Color;
use Filament\Widgets\AccountWidget;
use Hammadzafar05\MobileBottomNav\MobileBottomNav;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\HtmlString;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AgencyPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('agency')
            ->path('agency')
            ->when(
                config('filament.panels.agency_domain'),
                fn (Panel $p) => $p->domain(config('filament.panels.agency_domain'))->path(''),
            )
            ->brandLogo(fn () => view('filament.agency.brand'))
            ->brandLogoHeight('3.5rem')
            ->login()
            ->passwordReset()
            ->registration(CustomRegister::class)
            ->profile(EditProfile::class)
            ->emailVerification()
            ->databaseTransactions()
            ->databaseNotifications()
            ->databaseNotificationsPolling('30s')
            ->colors([
                'primary' => Color::hex('#2563eb'), // Bleu Agence
            ])
            ->multiFactorAuthentication([
                AppAuthentication::make()
                    ->recoverable()
                    ->recoveryCodeCount(10)
                    ->regenerableRecoveryCodes(false)
                    ->brandName('KeyHome Agency App'),
                EmailAuthentication::make(),
            ], isRequired: true)
            ->tenant(Agency::class)
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
                FilamentAuthenticate::class,
            ])
            ->renderHook(
                'panels::head.end',
                fn () => view('pwa.head-meta', ['themeColor' => '#2563eb']),
            )
            ->renderHook(
                'panels::head.end',
                fn () => new HtmlString('
                    <!-- Dynamic Island / Notch — viewport-fit=cover requis pour env(safe-area-inset-top) -->
                    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
                    <style>
                        .fi-topbar {
                            padding-top: env(safe-area-inset-top) !important;
                        }
                        .fi-sidebar-header {
                            padding-top: calc(env(safe-area-inset-top) + 1rem) !important;
                        }
                        body {
                            padding-bottom: env(safe-area-inset-bottom);
                        }
                    </style>
                '),
            )
            ->renderHook(
                'panels::head.end',
                fn () => new HtmlString('<style>.fi-no { z-index: 9999 !important; }</style>'),
            )
            ->renderHook(
                'panels::body.end',
                fn () => view('pwa.splash'),
            )
            ->renderHook(
                'panels::body.end',
                fn () => view('pwa.register-sw'),
            )
            ->renderHook(
                'panels::body.end',
                fn () => view('filament.mobile-bridge'),
            )
            ->assets([
                // Bridge natif minimal
                Js::make('filament-native-bridge', resource_path('js/filament-native-bridge.js')),
            ])
            ->plugins([
                MobileBottomNav::make()
                    ->fromNavigation(5)
                    ->moreButton(true)
                    ->moreButtonLabel('Menu'),
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
