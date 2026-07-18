<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExpiryRiskReportFilamentWorkspaceTest extends TestCase
{
    public function test_expiry_risk_report_uses_a_focused_filament_risk_workspace(): void
    {
        $table = file_get_contents(app_path('Filament/Resources/ExpiryRiskReports/Tables/ExpiryRiskReportsTable.php'));

        foreach ([
            'FiltersLayout::Modal',
            "Section::make('الصلاحية ومستوى الخطورة')",
            "Section::make('موقع المخزون')",
            "Section::make('المنتج والتشغيلة')",
            "->label('خيارات التقرير')",
            "->modalHeading('خيارات تصفية تقرير المواد القريبة من الانتهاء')",
            'ColumnManagerLayout::Modal',
            '->columnManagerColumns(2)',
            "->label('الأعمدة')",
            "->modalHeading('إدارة أعمدة تقرير المواد القريبة من الانتهاء')",
            '->persistSearchInSession()',
            '->persistColumnSearchesInSession()',
            '->persistFiltersInSession()',
            '->persistSortInSession()',
            '->paginationPageOptions([10, 25, 50, 100])',
            '->defaultPaginationPageOption(25)',
            '->stackedOnMobile()',
            "->emptyStateHeading('لا توجد أرصدة ضمن نطاق مخاطر الصلاحية')",
        ] as $expected) {
            $this->assertStringContainsString($expected, $table);
        }
    }

    public function test_expiry_risk_report_keeps_existing_filters_calculations_printing_and_scope(): void
    {
        $table = file_get_contents(app_path('Filament/Resources/ExpiryRiskReports/Tables/ExpiryRiskReportsTable.php'));
        $page = file_get_contents(app_path('Filament/Resources/ExpiryRiskReports/Pages/ManageExpiryRiskReports.php'));
        $resource = file_get_contents(app_path('Filament/Resources/ExpiryRiskReports/ExpiryRiskReportResource.php'));

        foreach ([
            "Filter::make('expiry_risk')",
            "SelectFilter::make('warehouse_id')",
            "SelectFilter::make('warehouse_type')",
            "SelectFilter::make('vehicle_id')",
            "SelectFilter::make('product_id')",
            "SelectFilter::make('category_id')",
            "Filter::make('batch_number')",
            "->default('risk_30')",
            'self::applyExpiryFilter($query, $data)',
        ] as $filter) {
            $this->assertStringContainsString($filter, $table);
        }

        foreach ([
            "TextColumn::make('expiry_status')",
            "TextColumn::make('days_remaining')",
            "TextColumn::make('quantity')",
            "TextColumn::make('inventory_value')",
            "TextColumn::make('average_unit_cost')",
            "->sum(DB::raw('quantity * average_unit_cost'))",
            'return (float) $record->quantity',
            '* (float) $record->average_unit_cost;',
            'pageCondition: false',
            'allTableCondition: true',
            "Action::make('print')",
            "route('reports.expiry-risk.print'",
            'PermissionName::REPORT_EXPIRY_RISK->value',
        ] as $expected) {
            $this->assertStringContainsString($expected, $table);
        }

        foreach ([
            "'expired_only' => self::applyExpiryStatus(\$query, 'expired')",
            "'within_7' => \$query",
            "'within_30' => \$query",
            "'missing' => self::applyExpiryStatus(\$query, 'missing')",
            "'critical_7' => \$query",
            "'near_30' => \$query",
            "'monitoring_60' => \$query",
            "'valid' => \$query",
            "\$days < 0 => 'expired'",
            "\$days <= 7 => 'critical_7'",
            "\$days <= 30 => 'near_30'",
            "\$days <= 60 => 'monitoring_60'",
        ] as $expected) {
            $this->assertStringContainsString($expected, $table);
        }

        foreach ([
            "Action::make('printFiltered')",
            "'reports.expiry-risk.print-filtered'",
            "'filters' => \$this->tableFilters ?? []",
            "'search' => \$this->getTableSearch()",
            'PermissionName::REPORT_EXPIRY_RISK->value',
        ] as $expected) {
            $this->assertStringContainsString($expected, $page);
        }

        foreach ([
            "->where('quantity', '>', 0)",
            "->where('has_expiry', true)",
            'PermissionName::REPORT_EXPIRY_RISK->value',
        ] as $expected) {
            $this->assertStringContainsString($expected, $resource);
        }
    }

    public function test_secondary_expiry_risk_columns_remain_available_but_hidden_by_default(): void
    {
        $table = file_get_contents(app_path('Filament/Resources/ExpiryRiskReports/Tables/ExpiryRiskReportsTable.php'));

        foreach ([
            'warehouse.type',
            'warehouse.vehicle.plate_number',
            'product.sku',
            'product.category.name_ar',
            'product.unit.name_ar',
            'average_unit_cost',
            'updated_at',
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
