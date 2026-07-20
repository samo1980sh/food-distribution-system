$ErrorActionPreference = 'Stop'

Write-Host '=== Operational Responsibility Separation ===' -ForegroundColor Cyan

php artisan migrate
if ($LASTEXITCODE -ne 0) { throw 'Migration failed.' }

php artisan db:seed --class=RolesAndPermissionsSeeder
if ($LASTEXITCODE -ne 0) { throw 'Permission seeding failed.' }

php artisan permission:cache-reset
if ($LASTEXITCODE -ne 0) { throw 'Permission cache reset failed.' }

php artisan optimize:clear
if ($LASTEXITCODE -ne 0) { throw 'Laravel cache clear failed.' }

$phpFiles = @(
    'app/Enums/OperationSource.php',
    'app/Enums/PermissionName.php',
    'app/Providers/Filament/AdminPanelProvider.php',
    'app/Services/Api/MobileOperationalWriteService.php',
    'app/Support/Authorization/RolePermissionMap.php',
    'database/migrations/2026_07_20_180000_add_operational_source_audit_columns.php',
    'tests/Feature/OperationalResponsibilitySeparationTest.php',
    'tests/Feature/RbacFoundationTest.php'
)

foreach ($file in $phpFiles) {
    php -l $file
    if ($LASTEXITCODE -ne 0) { throw "PHP syntax check failed: $file" }
}

php artisan test --filter=OperationalResponsibilitySeparationTest
if ($LASTEXITCODE -ne 0) { throw 'Operational responsibility tests failed.' }

php artisan test --filter=RbacFoundationTest
if ($LASTEXITCODE -ne 0) { throw 'RBAC tests failed.' }

php artisan test --filter=MobileOperationalWriteApiTest
if ($LASTEXITCODE -ne 0) { throw 'Mobile operational write tests failed.' }

php artisan test --filter=MobileOfflineSyncPushBatchTest
if ($LASTEXITCODE -ne 0) { throw 'Push batch tests failed.' }

php artisan test --filter=SalesInvoiceFilamentWorkflowTest
if ($LASTEXITCODE -ne 0) { throw 'Sales invoice workspace tests failed.' }

php artisan test --filter=SalesReturnPaymentFilamentWorkflowTest
if ($LASTEXITCODE -ne 0) { throw 'Sales return/payment workspace tests failed.' }

php artisan test --filter=VehicleLoadExpenseFilamentWorkflowTest
if ($LASTEXITCODE -ne 0) { throw 'Vehicle load/expense workspace tests failed.' }

php artisan test --filter=DailyClosingFilamentWorkspaceTest
if ($LASTEXITCODE -ne 0) { throw 'Daily closing workspace tests failed.' }

php artisan test
if ($LASTEXITCODE -ne 0) { throw 'Full Laravel test suite failed.' }

git diff --check
if ($LASTEXITCODE -ne 0) { throw 'git diff --check failed.' }

git status --short
