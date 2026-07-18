<?php

namespace Tests\Feature;

use Tests\TestCase;

class MasterDataUserFilamentWorkspaceTest extends TestCase
{
    public function test_user_resource_exposes_full_page_access_workspace(): void
    {
        $resource = file_get_contents(app_path('Filament/Resources/Users/UserResource.php'));
        $form = file_get_contents(app_path('Filament/Resources/Users/Schemas/UserForm.php'));
        $infolist = file_get_contents(app_path('Filament/Resources/Users/Schemas/UserInfolist.php'));
        $table = file_get_contents(app_path('Filament/Resources/Users/Tables/UsersTable.php'));

        $this->assertStringContainsString("'index' => ListUsers::route('/')", $resource);
        $this->assertStringContainsString("'create' => CreateUser::route('/create')", $resource);
        $this->assertStringContainsString("'view' => ViewUser::route('/{record}')", $resource);
        $this->assertStringContainsString("'edit' => EditUser::route('/{record}/edit')", $resource);
        $this->assertStringContainsString('UserInfolist::configure', $resource);
        $this->assertFileDoesNotExist(app_path('Filament/Resources/Users/Pages/ManageUsers.php'));

        $this->assertStringContainsString("Section::make('بيانات الحساب')", $form);
        $this->assertStringContainsString("Section::make('الأدوار وقناة العمل')", $form);
        $this->assertStringContainsString("Section::make('نطاقات الوصول المباشرة')", $form);
        $this->assertStringContainsString("Section::make('الحالة والأمان')", $form);
        $this->assertStringContainsString('AllowedUserRoleCombination', $form);
        $this->assertStringContainsString('accessAreas', $form);
        $this->assertStringContainsString('accessWarehouses', $form);

        $this->assertStringContainsString("Section::make('نطاق الوصول الفعلي')", $infolist);
        $this->assertStringContainsString('AccessScopeService', $infolist);
        $this->assertStringContainsString("Section::make('الصلاحيات والأمان')", $infolist);

        $this->assertStringContainsString('recordUrl', $table);
        $this->assertStringContainsString('ActionGroup::make', $table);
        $this->assertStringContainsString('UserActions::activate()', $table);
        $this->assertStringContainsString('UserActions::deactivate()', $table);
        $this->assertStringContainsString('persistSearchInSession', $table);
        $this->assertStringContainsString('persistFiltersInSession', $table);
        $this->assertStringContainsString('emptyStateHeading', $table);
        $this->assertStringNotContainsString('DeleteAction', $table);
    }

    public function test_master_data_resources_keep_fast_slide_over_workflow_with_structured_forms(): void
    {
        $resources = [
            'Areas' => ['AreaForm.php', 'AreasTable.php', 'ManageAreas.php'],
            'Customers' => ['CustomerForm.php', 'CustomersTable.php', 'ManageCustomers.php'],
            'DistributionRoutes' => ['DistributionRouteForm.php', 'DistributionRoutesTable.php', 'ManageDistributionRoutes.php'],
            'ProductCategories' => ['ProductCategoryForm.php', 'ProductCategoriesTable.php', 'ManageProductCategories.php'],
            'Products' => ['ProductForm.php', 'ProductsTable.php', 'ManageProducts.php'],
            'Units' => ['UnitForm.php', 'UnitsTable.php', 'ManageUnits.php'],
            'Warehouses' => ['WarehouseForm.php', 'WarehousesTable.php', 'ManageWarehouses.php'],
            'Vehicles' => ['VehicleForm.php', 'VehiclesTable.php', 'ManageVehicles.php'],
            'Employees' => ['EmployeeForm.php', 'EmployeesTable.php', 'ManageEmployees.php'],
        ];

        foreach ($resources as $resource => [$formFile, $tableFile, $pageFile]) {
            $base = app_path("Filament/Resources/{$resource}");
            $form = file_get_contents($base.'/Schemas/'.$formFile);
            $table = file_get_contents($base.'/Tables/'.$tableFile);
            $page = file_get_contents($base.'/Pages/'.$pageFile);

            $this->assertStringContainsString('Section::make', $form, $resource.' form is not sectioned.');
            $this->assertStringContainsString('ActionGroup::make', $table, $resource.' table does not group actions.');
            $this->assertStringContainsString('MasterDataStatusActions::activate', $table, $resource.' table has no activate action.');
            $this->assertStringContainsString('MasterDataStatusActions::deactivate', $table, $resource.' table has no deactivate action.');
            $this->assertStringContainsString('persistSearchInSession', $table, $resource.' table does not persist search.');
            $this->assertStringContainsString('persistFiltersInSession', $table, $resource.' table does not persist filters.');
            $this->assertStringContainsString('persistSortInSession', $table, $resource.' table does not persist sorting.');
            $this->assertStringContainsString('emptyStateHeading', $table, $resource.' table has no empty state.');
            $this->assertStringNotContainsString('DeleteAction', $table, $resource.' table exposes destructive deletion.');
            $this->assertStringContainsString('->slideOver()', $page, $resource.' create action is not a slide-over.');
            $this->assertStringContainsString('->slideOver()', $table, $resource.' edit action is not a slide-over.');
        }
    }

    public function test_master_data_status_actions_deactivate_without_deleting_history(): void
    {
        $actions = file_get_contents(app_path('Support/Filament/MasterDataStatusActions.php'));

        $this->assertStringContainsString("name: 'activate'", $actions);
        $this->assertStringContainsString("name: 'deactivate'", $actions);
        $this->assertStringContainsString('Action::make($name)', $actions);
        $this->assertStringContainsString("forceFill(['status' => \$toStatus])->save()", $actions);
        $this->assertStringContainsString("Gate::authorize('update', \$record)", $actions);
        $this->assertStringNotContainsString('delete()', $actions);
    }
}
