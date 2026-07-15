$ErrorActionPreference = 'Stop'

$paths = @(
    'app/Filament/Resources/Employees/Schemas/EmployeeForm.php'
    'app/Filament/Resources/Users/Pages/ManageUsers.php'
    'app/Filament/Resources/Users/Schemas/UserForm.php'
    'app/Filament/Resources/Users/Tables/UsersTable.php'
    'app/Models/Employee.php'
    'app/Models/User.php'
    'app/Policies/CustomerPaymentPolicy.php'
    'app/Policies/DailyClosingPolicy.php'
    'app/Policies/PermissionPolicy.php'
    'app/Policies/SalesInvoicePolicy.php'
    'app/Policies/SalesReturnPolicy.php'
    'app/Policies/VehicleExpensePolicy.php'
    'app/Policies/VehicleLoadPolicy.php'
    'app/Providers/AccessScopeServiceProvider.php'
    'app/Services/Authorization/AccessScopeService.php'
    'app/Services/Authorization/UserScopeAssignmentService.php'
    'app/Services/Dashboard/ExecutiveDashboardService.php'
    'app/Services/Reports/OverdueCustomerReportService.php'
    'app/Services/Reports/RoutePerformanceReportService.php'
    'app/Services/Reports/TopCustomerReportService.php'
    'app/Support/Authorization/EffectiveAccessScope.php'
    'app/Support/Authorization/ScopedModelObserver.php'
    'app/Support/Authorization/ScopedModelRegistry.php'
    'bootstrap/providers.php'
    'database/migrations/2026_07_15_150000_create_user_access_scope_tables.php'
    'docs/rbac/DATA_SCOPES_AR.md'
    'docs/rbac/DATA_SCOPES_INSTALL_AR.md'
    'docs/rbac/DATA_SCOPES_MANIFEST.txt'
    'scripts/data-scopes-git-add.ps1'
    'tests/Feature/RoleDataScopeTest.php'
)

foreach ($path in $paths) {
    if (-not (Test-Path $path)) {
        throw "Required Role Data Scopes path is missing: $path"
    }

    git add -- $path

    if ($LASTEXITCODE -ne 0) {
        throw "git add failed for: $path"
    }
}

Write-Host ''
Write-Host 'Staged Role Data Scopes paths successfully.' -ForegroundColor Green
Write-Host 'No git add . was used.' -ForegroundColor Green
Write-Host ''
git status --short
