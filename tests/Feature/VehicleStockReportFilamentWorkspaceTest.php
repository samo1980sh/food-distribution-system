<?php

namespace Tests\Feature;

use Tests\TestCase;

class VehicleStockReportFilamentWorkspaceTest extends TestCase
{
    public function test_vehicle_stock_report_uses_a_focused_filament_inventory_workspace(): void
    {
        $table = file_get_contents(app_path('Filament/Resources/VehicleStockReports/Tables/VehicleStockReportsTable.php'));

        foreach ([
            'FiltersLayout::Modal',
            "Section::make('السيارة والمنتج')",
            "Section::make('الصلاحية')",
            'filtersTriggerAction',
            'filtersApplyAction',
            'FiltersResetActionPosition::Footer',
            'ColumnManagerLayout::Modal',
            'columnManagerColumns(2)',
            'columnManagerTriggerAction',
            'ColumnManagerResetActionPosition::Footer',
            'stackedOnMobile',
            'persistSearchInSession',
            'persistColumnSearchesInSession',
            'persistFiltersInSession',
            'persistSortInSession',
            'paginationPageOptions([10, 25, 50, 100])',
            'defaultPaginationPageOption(25)',
            "emptyStateHeading('لا توجد أرصدة في تقرير مخزون السيارات')",
        ] as $workspaceElement) {
            $this->assertStringContainsString($workspaceElement, $table);
        }
    }

    public function test_vehicle_stock_report_keeps_existing_filters_scope_printing_and_summaries(): void
    {
        $table = file_get_contents(app_path('Filament/Resources/VehicleStockReports/Tables/VehicleStockReportsTable.php'));
        $page = file_get_contents(app_path('Filament/Resources/VehicleStockReports/Pages/ManageVehicleStockReports.php'));
        $resource = file_get_contents(app_path('Filament/Resources/VehicleStockReports/VehicleStockReportResource.php'));

        foreach ([
            "SelectFilter::make('vehicle_id')",
            "SelectFilter::make('product_id')",
            "Filter::make('expiry_date')",
            "SelectFilter::make('expiry_status')",
        ] as $filter) {
            $this->assertStringContainsString($filter, $table);
        }

        foreach ([
            "TextColumn::make('quantity')",
            "TextColumn::make('average_unit_cost')",
            "TextColumn::make('inventory_value')",
            '(float) $record->quantity',
            '(float) $record->average_unit_cost',
            'Count::make()',
            'Sum::make()',
        ] as $inventoryElement) {
            $this->assertStringContainsString($inventoryElement, $table);
        }

        $this->assertStringContainsString("'reports.vehicle-stock.vehicle.print'", $table);
        $this->assertStringContainsString("'reports.vehicle-stock.print-filtered'", $page);
        $this->assertStringContainsString("->where('quantity', '!=', 0)", $resource);
        $this->assertStringContainsString("->where('type', 'vehicle')", $resource);
        $this->assertStringContainsString('->summaries(', $table);
        $this->assertStringContainsString('allTableCondition: true', $table);
    }

    public function test_secondary_vehicle_stock_columns_remain_available_but_hidden_by_default(): void
    {
        $table = file_get_contents(app_path('Filament/Resources/VehicleStockReports/Tables/VehicleStockReportsTable.php'));

        foreach ([
            'warehouse.name',
            'product.sku',
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
