<?php

namespace Tests\Feature;

use Tests\TestCase;

class VehicleLoadExpenseFilamentWorkflowTest extends TestCase
{
    public function test_vehicle_load_resource_exposes_full_page_workflow(): void
    {
        $resource = file_get_contents(app_path('Filament/Resources/VehicleLoads/VehicleLoadResource.php'));

        $this->assertStringContainsString("'index' => ListVehicleLoads::route('/')", $resource);
        $this->assertStringContainsString("'create' => CreateVehicleLoad::route('/create')", $resource);
        $this->assertStringContainsString("'view' => ViewVehicleLoad::route('/{record}')", $resource);
        $this->assertStringContainsString("'edit' => EditVehicleLoad::route('/{record}/edit')", $resource);
        $this->assertStringContainsString('VehicleLoadInfolist::configure', $resource);
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
        $this->assertStringContainsString("Section::make('بيانات المصروف')", $expenseForm);
        $this->assertStringContainsString("Section::make('الإيصال والملاحظات')", $expenseForm);
        $this->assertStringContainsString("Section::make('سجل الاعتماد والمراجعة')", $expenseInfolist);
    }
}
