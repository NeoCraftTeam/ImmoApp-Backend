<?php

declare(strict_types=1);

namespace App\Providers\Filament;

use App\Auth\MultiFactor\CacheEmailAuthentication;
use App\Enums\AdStatus;
use App\Filament\Admin\Pages\Auth\AdminLogin;
use App\Filament\Admin\Pages\Dashboard;
use App\Filament\Admin\Pages\ForcePasswordChange;
use App\Filament\Admin\Resources\Ads\AdResource;
use App\Filament\Admin\Resources\Payments\PaymentResource;
use App\Filament\Admin\Resources\PendingAds\PendingAdResource;
use App\Filament\Admin\Resources\Users\UserResource;
use App\Filament\Admin\Widgets\RegistrationsByAcquisitionChart;
use App\Filament\Admin\Widgets\StatsOverview;
use App\Filament\Admin\Widgets\UserChart;
use App\Filament\Admin\Widgets\UserStatusChart;
use App\Filament\Pages\Auth\EditProfile;
use App\Http\Middleware\FilamentAuthenticate;
use App\Http\Middleware\RequirePasswordChange;
use App\Models\Ad;
use Filament\Auth\MultiFactor\App\AppAuthentication;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Hammadzafar05\MobileBottomNav\MobileBottomNav;
use Hammadzafar05\MobileBottomNav\MobileBottomNavItem;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\HtmlString;
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
            ->login(AdminLogin::class)
            ->multiFactorAuthentication([
                AppAuthentication::make()
                    ->recoverable()
                    ->recoveryCodeCount(10)
                    ->regenerableRecoveryCodes(false)
                    ->brandName('KeyHome Admin'),
                CacheEmailAuthentication::make()
                    ->codeExpiryMinutes(30),
            ], isRequired: true)
            ->passwordReset()
            ->emailVerification()
            ->emailChangeVerification()
            ->globalSearch(true)
            ->profile(EditProfile::class)
            ->sidebarCollapsibleOnDesktop()
            ->font('poppins')
            ->brandLogo(fn () => view('filament.admin.brand'))
            ->brandLogoHeight('2.25rem')
            ->authGuard('web')
            ->renderHook(
                'panels::head.end',
                fn () => view('pwa.head-meta', ['themeColor' => '#F6475F']),
            )
            ->renderHook(
                'panels::head.end',
                fn () => new HtmlString('
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
                'panels::body.end',
                fn () => view('pwa.splash'),
            )
            ->renderHook(
                'panels::scripts.after',
                fn () => new HtmlString('
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
                'panels::head.end',
                fn () => view('pwa.unregister-sw'),
            )
            ->renderHook(
                'panels::auth.login.form.after',
                fn () => view('filament.admin.components.passkey-login-button'),
            )
            ->databaseTransactions()
            ->databaseNotifications()
            ->databaseNotificationsPolling('30s')
            ->unsavedChangesAlerts()
            ->collapsibleNavigationGroups(true)
            ->navigationGroups([
                // ── Core ─────────────────────────────────────────────────────────
                NavigationGroup::make('Annonces')
                    ->icon('heroicon-o-megaphone'),
                NavigationGroup::make('Membres')
                    ->icon('heroicon-o-user-group'),
                NavigationGroup::make('Finances')
                    ->icon('heroicon-o-banknotes'),
                NavigationGroup::make('Abonnements')
                    ->icon('heroicon-o-rectangle-stack'),
                NavigationGroup::make('Crédits')
                    ->icon('heroicon-o-sparkles'),
                // ── Secondary ───────────────────────────────────────────────
                NavigationGroup::make('Catalogue')
                    ->icon('heroicon-o-tag'),
                NavigationGroup::make('Marketing')
                    ->icon('heroicon-o-envelope')
                    ->collapsed(),
                NavigationGroup::make('Analytique')
                    ->icon('heroicon-o-chart-pie')
                    ->collapsed(),
                NavigationGroup::make('Contrats & Réservations')
                    ->icon('heroicon-o-document-check')
                    ->collapsed(),
                // ── Admin ─────────────────────────────────────────────────────
                NavigationGroup::make('Sécurité')
                    ->icon('heroicon-o-lock-closed')
                    ->collapsed(),
                NavigationGroup::make('Audit')
                    ->icon('heroicon-o-shield-check')
                    ->collapsed(),
                NavigationGroup::make('Configuration')
                    ->icon('heroicon-o-cog-6-tooth')
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
                ForcePasswordChange::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Admin/Widgets'), for: 'App\Filament\Admin\Widgets')
            ->widgets([
                StatsOverview::class,
                UserChart::class,
                UserStatusChart::class,
                RegistrationsByAcquisitionChart::class,
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
                RequirePasswordChange::class,
            ])
            ->plugins([
                MobileBottomNav::make()
                    ->items([
                        MobileBottomNavItem::make('À valider')
                            ->icon('heroicon-o-clipboard-document-check')
                            ->url(fn () => PendingAdResource::getUrl())
                            ->badge($this->getPendingAdsBadge())
                            ->badgeColor('danger')
                            ->isActive(fn () => request()->routeIs('filament.admin.resources.pending-ads.*')),

                        MobileBottomNavItem::make('Annonces')
                            ->icon('heroicon-o-megaphone')
                            ->url(fn () => AdResource::getUrl())
                            ->isActive(fn () => request()->routeIs('filament.admin.resources.ads.*')),

                        MobileBottomNavItem::make('Tableau de bord')
                            ->icon('heroicon-o-home')
                            ->url(fn () => Dashboard::getUrl())
                            ->isActive(fn () => request()->routeIs('filament.admin.pages.dashboard')),

                        MobileBottomNavItem::make('Utilisateurs')
                            ->icon('heroicon-o-users')
                            ->url(fn () => UserResource::getUrl())
                            ->isActive(fn () => request()->routeIs('filament.admin.resources.users.*')),

                        MobileBottomNavItem::make('Transactions')
                            ->icon('heroicon-o-banknotes')
                            ->url(fn () => PaymentResource::getUrl())
                            ->isActive(fn () => request()->routeIs('filament.admin.resources.payments.*')),
                    ])
                    ->moreButton(true)
                    ->moreButtonLabel('Menu'),
            ]);
    }

    /**
     * Get pending ads count for nav badge. Returns null when tables don't exist (e.g. during migrate).
     */
    private function getPendingAdsBadge(): ?string
    {
        try {
            $count = Ad::where('status', AdStatus::PENDING)->count();

            return $count > 0 ? (string) $count : null;
        } catch (\Throwable) {
            return null;
        }
    }
}
