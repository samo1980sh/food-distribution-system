<?php

namespace Tests\Feature;

use Tests\TestCase;

class DailyClosingFilamentWorkspaceTest extends TestCase
{
    public function test_daily_closing_resource_exposes_full_page_workspace(): void
    {
        $resource = file_get_contents(app_path('Filament/Resources/DailyClosings/DailyClosingResource.php'));

        $this->assertStringContainsString("'index' => ListDailyClosings::route('/')", $resource);
        $this->assertStringContainsString("'create' => CreateDailyClosing::route('/create')", $resource);
        $this->assertStringContainsString("'view' => ViewDailyClosing::route('/{record}')", $resource);
        $this->assertStringContainsString("'edit' => EditDailyClosing::route('/{record}/edit')", $resource);
        $this->assertStringContainsString('DailyClosingInfolist::configure', $resource);
        $this->assertFileDoesNotExist(app_path('Filament/Resources/DailyClosings/Pages/ManageDailyClosings.php'));
    }

    public function test_daily_closing_workspace_uses_structured_reconciliation_components(): void
    {
        $form = file_get_contents(app_path('Filament/Resources/DailyClosings/Schemas/DailyClosingForm.php'));
        $infolist = file_get_contents(app_path('Filament/Resources/DailyClosings/Schemas/DailyClosingInfolist.php'));
        $actions = file_get_contents(app_path('Filament/Resources/DailyClosings/Actions/DailyClosingActions.php'));

        $this->assertStringContainsString("Section::make('نطاق الإغلاق')", $form);
        $this->assertStringContainsString("Section::make('مطابقة الصندوق')", $form);
        $this->assertStringContainsString("Section::make('الجرد الفعلي للمواد')", $form);
        $this->assertStringContainsString("Section::make('مطابقة المخزون الدفتري')", $infolist);
        $this->assertStringContainsString("Section::make('ملخص المبيعات والتحصيلات والمصاريف')", $infolist);
        $this->assertStringContainsString("Section::make('تفاصيل جرد المواد')", $infolist);
        $this->assertStringContainsString('RepeatableEntry::make', $infolist);
        $this->assertStringContainsString("Action::make('refreshTotals')", $actions);
        $this->assertStringContainsString("Action::make('confirm')", $actions);
        $this->assertStringContainsString("Action::make('cancel')", $actions);
    }

    public function test_daily_closing_table_is_persistent_and_action_oriented(): void
    {
        $table = file_get_contents(app_path('Filament/Resources/DailyClosings/Tables/DailyClosingsTable.php'));

        $this->assertStringContainsString('recordUrl', $table);
        $this->assertStringContainsString('ActionGroup::make', $table);
        $this->assertStringContainsString('persistSearchInSession', $table);
        $this->assertStringContainsString('persistFiltersInSession', $table);
        $this->assertStringContainsString('persistSortInSession', $table);
        $this->assertStringContainsString('emptyStateHeading', $table);
        $this->assertStringContainsString("Filter::make('closing_date')", $table);
    }
}
