<?php

namespace Tests\Feature;

use App\Models\Unit;
use App\Models\VehicleLoadItem;
use App\Support\Formatting\QuantityFormatter;
use Tests\TestCase;

class VehicleLoadExpenseFilamentWorkflowTest extends TestCase
{
    public function test_vehicle_load_resource_uses_slide_over_create_and_full_page_review_workflow(): void
    {
        $resource = file_get_contents(app_path('Filament/Resources/VehicleLoads/VehicleLoadResource.php'));
        $listPage = file_get_contents(app_path('Filament/Resources/VehicleLoads/Pages/ListVehicleLoads.php'));

        $this->assertStringContainsString("'index' => ListVehicleLoads::route('/')", $resource);
        $this->assertStringNotContainsString("'create' =>", $resource);
        $this->assertStringContainsString("'view' => ViewVehicleLoad::route('/{record}')", $resource);
        $this->assertStringContainsString("'edit' => EditVehicleLoad::route('/{record}/edit')", $resource);
        $this->assertStringContainsString('VehicleLoadInfolist::configure', $resource);
        $this->assertStringContainsString('CreateAction::make()', $listPage);
        $this->assertStringContainsString('->slideOver()', $listPage);
    }

    public function test_vehicle_load_actions_refresh_the_record_after_approval_and_cancellation(): void
    {
        $actions = file_get_contents(app_path('Filament/Resources/VehicleLoads/Actions/VehicleLoadActions.php'));

        $this->assertSame(2, substr_count($actions, 'self::refreshRecord($record);'));
        $this->assertStringContainsString('$record->refresh();', $actions);
        $this->assertStringContainsString("'items.product'", $actions);
        $this->assertStringContainsString("'approver'", $actions);
    }

    public function test_vehicle_expense_resource_uses_hybrid_slide_over_workflow(): void
    {
        $resource = file_get_contents(app_path('Filament/Resources/VehicleExpenses/VehicleExpenseResource.php'));
        $managePage = file_get_contents(app_path('Filament/Resources/VehicleExpenses/Pages/ManageVehicleExpenses.php'));
        $viewPage = file_get_contents(app_path('Filament/Resources/VehicleExpenses/Pages/ViewVehicleExpense.php'));
        $table = file_get_contents(app_path('Filament/Resources/VehicleExpenses/Tables/VehicleExpensesTable.php'));

        $this->assertStringContainsString("'index' => ManageVehicleExpenses::route('/')", $resource);
        $this->assertStringContainsString("'view' => ViewVehicleExpense::route('/{record}')", $resource);
        $this->assertStringNotContainsString("'create' =>", $resource);
        $this->assertStringNotContainsString("'edit' =>", $resource);
        $this->assertStringContainsString('VehicleExpenseInfolist::configure', $resource);
        $this->assertStringContainsString('CreateAction::make()', $managePage);
        $this->assertStringContainsString('->slideOver()', $managePage);
        $this->assertStringContainsString('EditAction::make()', $table);
        $this->assertStringContainsString('->slideOver()', $table);
        $this->assertStringContainsString('EditAction::make()', $viewPage);
        $this->assertStringContainsString('->slideOver()', $viewPage);
    }

    public function test_load_and_expense_workspaces_use_persistent_tables_sections_and_action_groups(): void
    {
        $loadTable = file_get_contents(app_path('Filament/Resources/VehicleLoads/Tables/VehicleLoadsTable.php'));
        $loadForm = file_get_contents(app_path('Filament/Resources/VehicleLoads/Schemas/VehicleLoadForm.php'));
        $loadInfolist = file_get_contents(app_path('Filament/Resources/VehicleLoads/Schemas/VehicleLoadInfolist.php'));
        $expenseTable = file_get_contents(app_path('Filament/Resources/VehicleExpenses/Tables/VehicleExpensesTable.php'));
        $expenseForm = file_get_contents(app_path('Filament/Resources/VehicleExpenses/Schemas/VehicleExpenseForm.php'));
        $expenseInfolist = file_get_contents(app_path('Filament/Resources/VehicleExpenses/Schemas/VehicleExpenseInfolist.php'));

        foreach ([$loadTable, $expenseTable] as $table) {
            $this->assertStringContainsString('persistSearchInSession', $table);
            $this->assertStringContainsString('persistFiltersInSession', $table);
            $this->assertStringContainsString('emptyStateHeading', $table);
            $this->assertStringContainsString('ActionGroup::make', $table);
        }

        $this->assertStringContainsString("Section::make('بيانات أمر التحميل')", $loadForm);
        $this->assertStringContainsString("Section::make('مواد التحميل')", $loadForm);
        $this->assertStringContainsString('RepeatableEntry::make', $loadInfolist);
        $this->assertStringContainsString("Section::make('استلام العهدة')", $loadInfolist);
        $this->assertStringContainsString("Section::make('تفاصيل استلام المواد')", $loadInfolist);
        $this->assertStringContainsString('QuantityFormatter::format(', $loadInfolist);
        $this->assertStringContainsString("TextEntry::make('handover_by')", $loadInfolist);
        $this->assertStringContainsString('handoverByLabel', $loadInfolist);
        $this->assertStringContainsString('QuantityFormatter::formatDifference', $loadInfolist);
        $this->assertStringContainsString('QuantityFormatter::format(', $loadTable);
        $this->assertStringContainsString("TextEntry::make('handover_status')", $loadInfolist);
        $this->assertStringContainsString("TextColumn::make('handover_status')", $loadTable);
        $this->assertStringContainsString("Section::make('بيانات المصروف')", $expenseForm);
        $this->assertStringContainsString("TextInput::make('odometer_reading')", $expenseForm);
        $this->assertStringContainsString("TextEntry::make('odometer_reading')", $expenseInfolist);
        $this->assertStringContainsString("TextColumn::make('odometer_reading')", $expenseTable);
        $this->assertStringContainsString("Section::make('الإيصال والملاحظات')", $expenseForm);
        $this->assertStringContainsString("Section::make('سجل الاعتماد والمراجعة')", $expenseInfolist);
    }

    public function test_vehicle_load_details_show_calculated_handover_differences_and_hide_repeatable_label(): void
    {
        $unit = new Unit([
            'code' => 'BAG',
            'name_ar' => 'كيس',
            'symbol' => 'كيس',
        ]);

        $this->assertSame('40', QuantityFormatter::format(40.000));
        $this->assertSame('38', QuantityFormatter::format(38.000));
        $this->assertSame('1.5', QuantityFormatter::format(1.500));
        $this->assertSame('2.25', QuantityFormatter::format(2.250));
        $this->assertSame('40 كيس', QuantityFormatter::formatWithUnit(40.000, $unit));
        $this->assertSame('38 كيس', QuantityFormatter::formatWithUnit(38.000, $unit));
        $this->assertSame('1.5 كيس', QuantityFormatter::formatWithUnit(1.500, $unit));
        $this->assertSame('2.25 كيس', QuantityFormatter::formatWithUnit(2.250, $unit));
        $this->assertSame('-2 كيس', QuantityFormatter::formatDifference(-2.000, $unit));
        $this->assertSame('+10 كيس', QuantityFormatter::formatDifference(10.000, $unit));
        $this->assertSame('0 كيس', QuantityFormatter::formatDifference(0.000, $unit));
        $this->assertSame('0', QuantityFormatter::formatDifference(0.000, null));
        $this->assertSame('40', QuantityFormatter::format($this->makeVehicleLoadItem(40, 38)->quantity));
    }

    private function makeVehicleLoadItem(float $loaded, float $received): VehicleLoadItem
    {
        return new VehicleLoadItem([
            'quantity' => $loaded,
            'received_quantity' => $received,
        ]);
    }
}
