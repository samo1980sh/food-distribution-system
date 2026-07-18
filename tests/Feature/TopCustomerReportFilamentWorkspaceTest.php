<?php

namespace Tests\Feature;

use Tests\TestCase;

class TopCustomerReportFilamentWorkspaceTest extends TestCase
{
    public function test_top_customer_report_uses_a_focused_filament_ranking_workspace(): void
    {
        $table = file_get_contents(app_path('Filament/Resources/TopCustomerReports/Tables/TopCustomerReportsTable.php'));

        foreach ([
            'FiltersLayout::Modal',
            "Section::make('الفترة والترتيب')",
            "Section::make('العميل ونطاق التوزيع')",
            "Section::make('خصائص العميل والبحث')",
            "->label('خيارات التقرير')",
            "->modalHeading('خيارات تصفية تقرير العملاء الأكثر شراءً')",
            'ColumnManagerLayout::Modal',
            '->columnManagerColumns(2)',
            "->label('الأعمدة')",
            "->modalHeading('إدارة أعمدة تقرير العملاء الأكثر شراءً')",
            '->persistSearchInSession()',
            '->persistColumnSearchesInSession()',
            '->persistFiltersInSession()',
            '->persistSortInSession()',
            '->paginationPageOptions([10, 25, 50, 100])',
            '->defaultPaginationPageOption(25)',
            '->stackedOnMobile()',
            "->emptyStateHeading('لا يوجد عملاء ضمن معايير الترتيب المحددة')",
            'self::customerDescription($record)',
            'self::returnsCountDescription($record, $livewire)',
            'self::profitMarginDescription($record, $livewire)',
            "->label('طباعة تفاصيل المشتريات')",
            "->tooltip('طباعة تفاصيل مشتريات العميل')",
        ] as $expected) {
            $this->assertStringContainsString($expected, $table);
        }
    }

    public function test_top_customer_report_keeps_existing_filters_rankings_calculations_printing_and_scope(): void
    {
        $table = file_get_contents(app_path('Filament/Resources/TopCustomerReports/Tables/TopCustomerReportsTable.php'));
        $page = file_get_contents(app_path('Filament/Resources/TopCustomerReports/Pages/ManageTopCustomerReports.php'));
        $resource = file_get_contents(app_path('Filament/Resources/TopCustomerReports/TopCustomerReportResource.php'));
        $service = file_get_contents(app_path('Services/Reports/TopCustomerReportService.php'));

        foreach ([
            "Filter::make('ranking_settings')",
            "DatePicker::make('from')",
            "DatePicker::make('until')",
            "Select::make('ranking_metric')",
            "Select::make('limit')",
            "Select::make('customer_id')",
            "Select::make('area_id')",
            "Select::make('route_id')",
            "Select::make('customer_type')",
            "Select::make('payment_type')",
            "Select::make('status')",
            "TextInput::make('minimum_net_sales')",
            "TextInput::make('search')",
            "->default('net_sales')",
            "->default('10')",
            '->normalizeSettings($data)',
            '->customerIds($settings)',
            '->whereIn(\'customers.id\', $ids)',
            'FIELD(customers.id, {$orderedIds})',
            '->default()',
        ] as $filter) {
            $this->assertStringContainsString($filter, $table);
        }

        foreach ([
            "TextColumn::make('ranking')",
            "TextColumn::make('invoice_count_report')",
            "TextColumn::make('gross_sales_report')",
            "TextColumn::make('return_count_report')",
            "TextColumn::make('returns_amount_report')",
            "TextColumn::make('net_sales_report')",
            "TextColumn::make('net_quantity_report')",
            "TextColumn::make('average_invoice_report')",
            "TextColumn::make('approximate_profit_report')",
            "TextColumn::make('profit_margin_report')",
            "TextColumn::make('net_sales_share_report')",
            "TextColumn::make('last_purchase_date_report')",
            "'invoice_count'",
            "'gross_sales'",
            "'returns_amount'",
            "'net_sales'",
            "'net_quantity'",
            "'approximate_profit'",
            'pageCondition: false',
            'allTableCondition: true',
            "Action::make('print')",
            "route('reports.top-customers.print'",
            'PermissionName::REPORT_TOP_CUSTOMERS->value',
        ] as $expected) {
            $this->assertStringContainsString($expected, $table);
        }

        foreach ([
            "Action::make('printFiltered')",
            "'reports.top-customers.print-filtered'",
            "'filters' => \$this->tableFilters ?? []",
            'PermissionName::REPORT_TOP_CUSTOMERS->value',
        ] as $expected) {
            $this->assertStringContainsString($expected, $page);
        }

        foreach ([
            "->with(['area', 'route'])",
            "->where('status', 'confirmed')",
            'PermissionName::REPORT_TOP_CUSTOMERS->value',
        ] as $expected) {
            $this->assertStringContainsString($expected, $resource);
        }

        foreach ([
            "'net_sales' => 'صافي المبيعات'",
            "'gross_sales' => 'إجمالي المبيعات'",
            "'invoice_count' => 'عدد الفواتير'",
            "'net_quantity' => 'صافي الكمية'",
            "'approximate_profit' => 'الربح التقريبي'",
            "'average_invoice' => 'متوسط قيمة الفاتورة'",
            "'10' => 'أفضل 10 عملاء'",
            "'all' => 'جميع العملاء'",
            "->where('status', 'confirmed')",
            "->whereDate('invoice_date', '>=', \$settings['from'])",
            "->whereDate('return_date', '>=', \$settings['from'])",
            "'gross_sales' => \$grossSales",
            "'returns_amount' => \$returnsAmount",
            "'net_sales' => \$netSales",
            "'net_quantity' => \$netQuantity",
            "'average_invoice' => \$invoices->count() > 0",
            "'approximate_profit' => \$approximateProfit",
            "'profit_margin_percent' => abs(\$netSales) > 0.0001",
            "\$summary['net_sales_share_percent'] = \$displayedNetSales > 0",
            "'approximate_profit' => (float) \$rankings->sum(",
        ] as $expected) {
            $this->assertStringContainsString($expected, $service);
        }
    }

    public function test_secondary_top_customer_columns_remain_available_but_hidden_by_default(): void
    {
        $table = file_get_contents(app_path('Filament/Resources/TopCustomerReports/Tables/TopCustomerReportsTable.php'));

        foreach ([
            'code',
            'area.name_ar',
            'route.name',
            'customer_type',
            'return_count_report',
            'net_quantity_report',
            'average_invoice_report',
            'profit_margin_report',
            'last_purchase_date_report',
        ] as $column) {
            $columnStart = strpos($table, "TextColumn::make('{$column}')");

            $this->assertNotFalse($columnStart, "The [{$column}] column is missing.");

            $nextColumnStart = strpos($table, 'TextColumn::make(', $columnStart + 1);
            $columnDefinition = substr(
                $table,
                $columnStart,
                $nextColumnStart === false ? null : $nextColumnStart - $columnStart,
            );

            $this->assertStringContainsString(
                'toggleable(isToggledHiddenByDefault: true)',
                $columnDefinition,
                "The [{$column}] column should remain available but hidden by default.",
            );
        }
    }
}
