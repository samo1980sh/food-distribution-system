<?php

namespace Tests\Feature;

use Tests\TestCase;

class OverdueCustomerReportFilamentWorkspaceTest extends TestCase
{
    public function test_overdue_customer_report_uses_a_focused_filament_credit_risk_workspace(): void
    {
        $table = file_get_contents(app_path('Filament/Resources/OverdueCustomerReports/Tables/OverdueCustomerReportsTable.php'));

        foreach ([
            'FiltersLayout::Modal',
            "Section::make('نطاق التقرير والاستحقاق')",
            "Section::make('نطاق التوزيع')",
            "Section::make('خصائص العميل')",
            "->label('خيارات التقرير')",
            "->modalHeading('خيارات تصفية تقرير العملاء المتأخرين')",
            'ColumnManagerLayout::Modal',
            '->columnManagerColumns(2)',
            "->label('الأعمدة')",
            "->modalHeading('إدارة أعمدة تقرير العملاء المتأخرين')",
            '->persistSearchInSession()',
            '->persistColumnSearchesInSession()',
            '->persistFiltersInSession()',
            '->persistSortInSession()',
            '->paginationPageOptions([10, 25, 50, 100])',
            '->defaultPaginationPageOption(25)',
            '->stackedOnMobile()',
            "->emptyStateHeading('لا يوجد عملاء ضمن نطاق التأخير المحدد')",
            'self::customerDescription($record)',
            "->label('مستوى المخاطر')",
            "->label('طباعة كشف المديونية')",
            "->tooltip('طباعة تفاصيل مديونية العميل')",
        ] as $expected) {
            $this->assertStringContainsString($expected, $table);
        }
    }

    public function test_overdue_customer_report_keeps_existing_filters_calculations_printing_and_scope(): void
    {
        $table = file_get_contents(app_path('Filament/Resources/OverdueCustomerReports/Tables/OverdueCustomerReportsTable.php'));
        $page = file_get_contents(app_path('Filament/Resources/OverdueCustomerReports/Pages/ManageOverdueCustomerReports.php'));
        $resource = file_get_contents(app_path('Filament/Resources/OverdueCustomerReports/OverdueCustomerReportResource.php'));
        $service = file_get_contents(app_path('Services/Reports/OverdueCustomerReportService.php'));

        foreach ([
            "Filter::make('overdue_settings')",
            "SelectFilter::make('area_id')",
            "SelectFilter::make('route_id')",
            "SelectFilter::make('payment_type')",
            "SelectFilter::make('customer_type')",
            "SelectFilter::make('status')",
            "->default('overdue')",
            'OverdueCustomerReportService::DEFAULT_CREDIT_DAYS',
            "->default(today())",
            "'minimum_overdue' => \$settings['minimum_overdue']",
            "->whereIn('customers.id', \$ids)",
            '->default()',
        ] as $filter) {
            $this->assertStringContainsString($filter, $table);
        }

        foreach ([
            "TextColumn::make('current_balance_report')",
            "TextColumn::make('overdue_amount_report')",
            "TextColumn::make('not_due_amount_report')",
            "TextColumn::make('overdue_invoices_count_report')",
            "TextColumn::make('oldest_overdue_date_report')",
            "TextColumn::make('days_overdue_report')",
            "TextColumn::make('credit_usage_report')",
            "TextColumn::make('risk_status_report')",
            "'current_balance'",
            "'overdue_amount'",
            "'not_due_amount'",
            'pageCondition: false',
            'allTableCondition: true',
            "Action::make('print')",
            "route('reports.overdue-customers.print'",
            'PermissionName::REPORT_OVERDUE_CUSTOMERS->value',
        ] as $expected) {
            $this->assertStringContainsString($expected, $table);
        }

        foreach ([
            "'scope' => in_array(",
            "['overdue', 'all_positive']",
            "'credit_days' => \$creditDays",
            "'as_of' => self::normalizeDate",
            "array_keys(OverdueCustomerReportService::riskOptions())",
            "'minimum_overdue' => max(",
            'min(max($creditDays, 1), 365)',
            'summaryForCustomer(',
            '->summaries(',
            "->sum(\$field)",
        ] as $expected) {
            $this->assertStringContainsString($expected, $table);
        }

        foreach ([
            "Action::make('printFiltered')",
            "'reports.overdue-customers.print-filtered'",
            "'filters' => \$this->tableFilters ?? []",
            "'search' => \$this->getTableSearch()",
            'PermissionName::REPORT_OVERDUE_CUSTOMERS->value',
        ] as $expected) {
            $this->assertStringContainsString($expected, $page);
        }

        foreach ([
            "->with(['area', 'route'])",
            "->where('status', 'confirmed')",
            'PermissionName::REPORT_OVERDUE_CUSTOMERS->value',
        ] as $expected) {
            $this->assertStringContainsString($expected, $resource);
        }

        foreach ([
            'public const DEFAULT_CREDIT_DAYS = 30;',
            "\$scope = (string) (\$criteria['scope'] ?? 'overdue')",
            "if (\$scope === 'all_positive')",
            "'overdue_amount' => \$overdueAmount",
            "'not_due_amount' => \$notDueAmount",
            "'overdue_invoices_count' => \$overdueRows->count()",
            "'oldest_overdue_date' => \$oldestOverdueDate",
            "'days_overdue' => \$daysOverdue",
            "'credit_usage_percent' => \$creditUsage",
            "'risk_status' => \$riskStatus",
            "'over_limit' => 'متجاوز للحد الائتماني'",
            "'high' => 'مخاطر مرتفعة'",
        ] as $expected) {
            $this->assertStringContainsString($expected, $service);
        }
    }

    public function test_secondary_overdue_customer_columns_remain_available_but_hidden_by_default(): void
    {
        $table = file_get_contents(app_path('Filament/Resources/OverdueCustomerReports/Tables/OverdueCustomerReportsTable.php'));

        foreach ([
            'code',
            'contact_phone',
            'area.name_ar',
            'route.name',
            'credit_limit',
            'not_due_amount_report',
            'credit_usage_report',
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
