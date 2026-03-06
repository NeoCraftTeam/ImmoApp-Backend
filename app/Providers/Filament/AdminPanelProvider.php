<?php

declare(strict_types=1);

namespace App\Providers\Filament;

use App\Filament\Admin\Widgets\StatsOverview;
use App\Filament\Admin\Widgets\UserChart;
use App\Filament\Admin\Widgets\UserStatusChart;
use DutchCodingCompany\FilamentSocialite\FilamentSocialitePlugin;
use DutchCodingCompany\FilamentSocialite\Provider;
use Filament\Auth\MultiFactor\App\AppAuthentication;
use Filament\Auth\MultiFactor\Email\EmailAuthentication;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->when(
                config('filament.panels.admin_domain'),
                fn (Panel $p) => $p->domain(config('filament.panels.admin_domain'))->path(''),
            )
            ->login()
            ->multiFactorAuthentication([
                AppAuthentication::make()
                    ->recoverable()
                    ->recoveryCodeCount(10)
                    ->regenerableRecoveryCodes(false)
                    ->brandName('KeyHome Admin'),
                EmailAuthentication::make(),
            ], isRequired: true)
            ->passwordReset()
            ->emailVerification()
            ->emailChangeVerification()
            ->globalSearch(true)
            ->profile(\App\Filament\Pages\Auth\EditProfile::class)
            ->sidebarCollapsibleOnDesktop()
            ->font('poppins')
            ->brandLogo(fn () => view('filament.admin.brand'))
            ->brandLogoHeight('2.25rem')
            ->authGuard('web')
            ->spa()
            ->renderHook(
                'panels::head.end',
                fn () => view('pwa.head-meta', ['themeColor' => '#F6475F']),
            )
            ->renderHook(
                'panels::body.end',
                fn () => view('pwa.splash'),
            )
            ->renderHook(
                'panels::scripts.after',
                fn () => new \Illuminate\Support\HtmlString('
                    <script>
                        window.addEventListener("error", function(e) {
                            if (e.message && e.message.includes("this.getChart().destroy")) {
                                e.preventDefault();
                            }
                        });
                    </script>
                '),
            )
            ->renderHook(
                'panels::scripts.after',
                fn () => view('pwa.register-sw'),
            )
            ->databaseNotifications()
            ->databaseNotificationsPolling('30s')
            ->unsavedChangesAlerts()
            ->collapsibleNavigationGroups(true)
            ->navigationGroups([
                \Filament\Navigation\NavigationGroup::make('Annonces')
                    ->icon('heroicon-o-home'),
                \Filament\Navigation\NavigationGroup::make('Villes & Quartiers')
                    ->icon('heroicon-o-map-pin'),
                \Filament\Navigation\NavigationGroup::make('Utilisateurs')
                    ->icon('heroicon-o-users'),
                \Filament\Navigation\NavigationGroup::make('Abonnements')
                    ->icon('heroicon-o-credit-card'),
                \Filament\Navigation\NavigationGroup::make('Système de Crédits')
                    ->icon('heroicon-o-star'),
                \Filament\Navigation\NavigationGroup::make('Configuration')
                    ->icon('heroicon-o-cog-6-tooth')
                    ->collapsed(),
                \Filament\Navigation\NavigationGroup::make('Administration')
                    ->icon('heroicon-o-shield-check')
                    ->collapsed(),
            ])
            ->viteTheme('resources/css/filament/admin/theme.css')
            ->colors([
                'primary' => Color::Amber,
            ])
            ->discoverResources(in: app_path('Filament/Admin/Resources'), for: 'App\Filament\Admin\Resources')
            ->discoverPages(in: app_path('Filament/Admin/Pages'), for: 'App\Filament\Admin\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Admin/Widgets'), for: 'App\Filament\Admin\Widgets')
            ->widgets([
                StatsOverview::class,
                UserChart::class,
                UserStatusChart::class,
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
                    ->registration(false)
                    ->rememberLogin(true)
                    ->showDivider(true),
            ]);
    }
}
