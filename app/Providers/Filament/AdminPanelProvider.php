<?php

namespace App\Providers\Filament;

use App\Filament\Pages\Dashboard;
use App\Filament\Widgets\AdminWelcomeWidget;
use App\Filament\Widgets\DistributionOverviewWidget;
use App\Filament\Widgets\ExecutiveRankingsWidget;
use App\Filament\Widgets\FinancialTrendChartWidget;
use App\Filament\Widgets\OperationalAlertsWidget;
use App\Filament\Widgets\OperationsFollowUpWidget;
use App\Filament\Widgets\RecentOperationsWidget;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Assets\Css;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\Width;
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
            ->login()
            ->brandName('FreshRoute | نظام التوزيع')
            ->colors([
                'primary' => Color::Teal,
                'success' => Color::Green,
                'warning' => Color::Amber,
                'danger' => Color::Red,
                'info' => Color::Blue,
                'gray' => Color::Slate,
            ])
            ->assets([
                Css::make('freshroute-admin-theme', resource_path('css/filament/admin/theme.css')),
            ])
            ->maxContentWidth(Width::Full)
            ->simplePageMaxContentWidth(Width::Large)
            ->navigationGroups([
                'التهيئة الأساسية',
                'المخزون',
                'التوزيع والأسطول',
                'التخطيط والتجهيز',
                'المراجعة والاعتماد',
                NavigationGroup::make()
                    ->label('تقارير المبيعات والعملاء')
                    ->collapsed(),
                NavigationGroup::make()
                    ->label('تقارير المركبات والتوزيع')
                    ->collapsed(),
                NavigationGroup::make()
                    ->label('تقارير المخزون والربحية')
                    ->collapsed(),
                NavigationGroup::make()
                    ->label('تقارير الرقابة اليومية')
                    ->collapsed(),
            ])
            ->spa()
            ->unsavedChangesAlerts()
            ->strictAuthorization()
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->widgets([
                AdminWelcomeWidget::class,
                DistributionOverviewWidget::class,
                FinancialTrendChartWidget::class,
                OperationalAlertsWidget::class,
                ExecutiveRankingsWidget::class,
                RecentOperationsWidget::class,
                OperationsFollowUpWidget::class,
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
            ]);
    }
}
