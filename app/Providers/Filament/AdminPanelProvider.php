<?php

namespace App\Providers\Filament;

use App\Filament\Widgets\CatalogStatsOverview;
use App\Filament\Widgets\RecentlyUpdatedProducts;
use App\Http\Middleware\SecurityHeaders;
use App\Services\SiteSettings;
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
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
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
            ->login()
            // The panel language and its text direction follow APP_LOCALE;
            // Filament ships the Arabic strings and switches to RTL with them.
            ->brandName(fn (): string => app(SiteSettings::class)->get()->translate('site_name')
                ?? config('app.name'))
            ->colors([
                'primary' => Color::Teal,
            ])
            // Saving a product writes the product and its image rows together,
            // so each resource write runs inside one transaction.
            ->databaseTransactions()
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([
                AccountWidget::class,
                CatalogStatsOverview::class,
                RecentlyUpdatedProducts::class,
            ])
            ->middleware([
                // The panel gets the plain hardening headers; the
                // Content-Security-Policy is storefront-only, because
                // Livewire and Filament depend on inline scripts.
                SecurityHeaders::class.':'.SecurityHeaders::WITHOUT_CSP,
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                // Logging out in one place invalidates the other sessions of
                // the same account.
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
