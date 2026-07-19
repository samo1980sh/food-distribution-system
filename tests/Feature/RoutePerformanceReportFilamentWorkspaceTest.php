<?php

namespace Tests\Feature;

use Tests\TestCase;

class RoutePerformanceReportFilamentWorkspaceTest extends TestCase
{
    public function test_route_performance_report_uses_four_clear_analysis_views(): void
    {
        $page = $this->source(
            'app/Filament/Resources/RoutePerformanceReports/Pages/ManageRoutePerformanceReports.php',
        );
        $view = $this->source(
            'resources/views/filament/resources/route-performance-reports/pages/manage-route-performance-reports.blade.php',
        );

        foreach ([
            "protected string \$view =",
            "public string \$analysisView = 'executive';",
            "public function setAnalysisView(string \$view): void",
            'public function getAnalysisViews(): array',
            'public function getAnalysisViewDescription(): string',
            "'executive' => [",
            "'sales' => [",
            "'collections' => [",
            "'operations' => [",
            "'النظرة التنفيذية'",
            "'المبيعات والربحية'",
            "'التحصيل والتسوية'",
            "'التغطية والتشغيل'",
            "array_key_exists(\$view, \$this->getAnalysisViews())",
            "\$this->analysisView = \$view;",
            "Action::make('printFiltered')",
            "'filters' => \$this->tableFilters ?? []",
        ] as $needle) {
            $this->assertStringContainsString($needle, $page);
        }

        foreach ([
            '<x-filament-panels::page>',
            '<x-filament::section>',
            '<x-filament::tabs label="زوايا تحليل أداء خطوط التوزيع">',
            '<x-filament::tabs.item',
            ':active="$this->analysisView === $viewKey"',
            ':icon="$view[\'icon\']"',
            'wire:click="setAnalysisView(\'{{ $viewKey }}\')"',
            '{{ $view[\'label\'] }}',
            '{{ $this->table }}',
            '</x-filament-panels::page>',
        ] as $needle) {
            $this->assertStringContainsString($needle, $view);
        }

        $this->assertStringNotContainsString('<style>', $view);
        $this->assertStringNotContainsString('theme.css', $page.$view);
    }

    public function test_each_analysis_view_has_a_focused_column_set_and_wide_filters(): void
    {
        $table = $this->source(
            'app/Filament/Resources/RoutePerformanceReports/Tables/RoutePerformanceReportsTable.php',
        );

        foreach ([
            "TextColumn::make('ranking')",
            "TextColumn::make('name')",
            "TextColumn::make('activity_report')",
            "TextColumn::make('area.name_ar')",
            "TextColumn::make('vehicle.plate_number')",
            "TextColumn::make('driver.name')",
            "TextColumn::make('salesRepresentative.name')",
            "TextColumn::make('assigned_customers_report')",
            "TextColumn::make('served_customers_report')",
            "TextColumn::make('service_coverage_report')",
            "TextColumn::make('invoice_count_report')",
            "TextColumn::make('net_sales_report')",
            "TextColumn::make('return_rate_report')",
            "TextColumn::make('gross_profit_report')",
            "TextColumn::make('vehicle_expenses_report')",
            "TextColumn::make('net_contribution_report')",
            "TextColumn::make('contribution_margin_report')",
            "TextColumn::make('total_collections_report')",
            "TextColumn::make('collection_coverage_report')",
            "TextColumn::make('loaded_quantity_report')",
            "TextColumn::make('cash_difference_report')",
        ] as $column) {
            $this->assertStringContainsString($column, $table);
        }

        foreach ([
            "self::isView(\$livewire, 'executive')",
            "self::isView(\$livewire, 'executive', 'collections')",
            "self::isView(\$livewire, 'operations')",
            "self::isView(\$livewire, 'executive', 'operations')",
            "self::isView(\$livewire, 'sales')",
            "self::isView(\$livewire, 'executive', 'sales', 'collections')",
            "self::isView(\$livewire, 'executive', 'sales')",
            "self::isView(\$livewire, 'executive', 'collections')",
            "self::isView(\$livewire, 'collections')",
            "self::isView(\$livewire, 'collections', 'operations')",
        ] as $visibility) {
            $this->assertStringContainsString($visibility, $table);
        }

        foreach ([
            'FiltersLayout::AboveContentCollapsible',
            "Filter::make('performance_settings')",
            "Section::make('الفترة والترتيب')",
            "Section::make('نطاق التوزيع')",
            "Section::make('فريق الخط')",
            "Section::make('حدود الأداء والبحث')",
            "->label('خيارات التقرير')",
            "->label('عرض النتائج')",
            '->filtersFormColumns(1)',
            '->filtersResetActionPosition(FiltersResetActionPosition::Footer)',
        ] as $filterLayout) {
            $this->assertStringContainsString($filterLayout, $table);
        }

        foreach ([
            "DatePicker::make('from')",
            "DatePicker::make('until')",
            "Select::make('ranking_metric')",
            "Select::make('scope')",
            "Select::make('limit')",
            "Select::make('status')",
            "Select::make('route_id')",
            "Select::make('area_id')",
            "Select::make('vehicle_id')",
            "Select::make('driver_id')",
            "Select::make('sales_representative_id')",
            "TextInput::make('minimum_net_sales')",
            "TextInput::make('minimum_contribution')",
            "TextInput::make('search')",
        ] as $filter) {
            $this->assertStringContainsString($filter, $table);
        }

        $this->assertStringNotContainsString('FiltersLayout::Modal', $table);
        $this->assertStringNotContainsString('columnManagerLayout', $table);
        $this->assertStringNotContainsString('modalWidth(', $table);
        $this->assertStringNotContainsString('toggleable(', $table);
    }

    public function test_route_performance_report_keeps_existing_rankings_calculations_printing_and_scope(): void
    {
        $table = $this->source(
            'app/Filament/Resources/RoutePerformanceReports/Tables/RoutePerformanceReportsTable.php',
        );
        $page = $this->source(
            'app/Filament/Resources/RoutePerformanceReports/Pages/ManageRoutePerformanceReports.php',
        );

        foreach ([
            'RoutePerformanceReportService::rankingMetricOptions()',
            'RoutePerformanceReportService::scopeOptions()',
            'RoutePerformanceReportService::limitOptions()',
            'RoutePerformanceReportService::statusOptions()',
            "->default('net_contribution')",
            "->default('all')",
            "->default('active')",
            '->normalizeSettings($data)',
            '->routeIds($settings)',
            "->whereIn('distribution_routes.id', \$ids)",
            'FIELD(distribution_routes.id, {$ordered})',
            '->default()',
            "Action::make('print')",
            "route('reports.route-performance.print'",
            "PermissionName::REPORT_ROUTE_PERFORMANCE->value",
            "'performance_settings'",
            '->summaryForRoute(',
            '->rankings(self::settingsFromLivewire($livewire))',
            "->whereIn('route_id', \$ids)",
            "->sum(\$field)",
            "'assigned_active_customers'",
            "'served_customers'",
            "'service_coverage_percent'",
            "'invoice_count'",
            "'net_sales'",
            "'return_rate_percent'",
            "'gross_profit'",
            "'vehicle_expenses'",
            "'net_contribution'",
            "'contribution_margin_percent'",
            "'total_collections'",
            "'collection_coverage_percent'",
            "'loaded_quantity'",
            "'cash_difference'",
            '->summaries(',
            'allTableCondition: true',
            '->persistFiltersInSession()',
            '->stackedOnMobile()',
        ] as $needle) {
            $this->assertStringContainsString($needle, $table);
        }

        foreach ([
            "'reports.route-performance.print-filtered'",
            "PermissionName::REPORT_ROUTE_PERFORMANCE->value",
            "'filters' => \$this->tableFilters ?? []",
        ] as $needle) {
            $this->assertStringContainsString($needle, $page);
        }

        $this->assertStringNotContainsString('RoutePerformanceReportService::forgetCache()', $table.$page);
        $this->assertStringNotContainsString('theme.css', $table.$page);
    }

    private function source(string $relativePath): string
    {
        $contents = file_get_contents(base_path($relativePath));

        $this->assertNotFalse($contents, "تعذر قراءة الملف [{$relativePath}].");

        return (string) $contents;
    }
}
