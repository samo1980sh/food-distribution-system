$ErrorActionPreference = 'Stop'

$expectedHead = '8a268af'
$currentHead = (git rev-parse --short HEAD).Trim()

if ($currentHead -ne $expectedHead) {
    throw "Expected HEAD $expectedHead but found $currentHead. Review before staging."
}

$paths = @(
    'composer.json',
    'composer.lock',
    'bootstrap/providers.php',
    'app/Enums',
    'app/Filament',
    'app/Http/Controllers/Reports',
    'app/Models/User.php',
    'app/Policies',
    'app/Providers/AppServiceProvider.php',
    'app/Providers/Filament/AdminPanelProvider.php',
    'app/Services/Dashboard/ExecutiveDashboardService.php',
    'app/Support',
    'database/migrations/2026_07_15_120000_create_permission_tables_and_migrate_legacy_roles.php',
    'database/migrations/2026_07_15_120100_enforce_single_employee_per_user.php',
    'database/seeders/DatabaseSeeder.php',
    'database/seeders/RolesAndPermissionsSeeder.php',
    'tests/TestCase.php',
    'tests/Feature/RbacFoundationTest.php',
    'docs/rbac/INSTALL_AR.md',
    'docs/rbac/ROLE_MATRIX_AR.md',
    'docs/rbac/MANIFEST.txt',
    'scripts/rbac-git-add.ps1'
)

foreach ($path in $paths) {
    if (-not (Test-Path $path)) {
        throw "Missing expected path: $path"
    }

    git add -- $path

    if ($LASTEXITCODE -ne 0) {
        throw "git add failed for: $path"
    }
}

Write-Host ''
Write-Host 'Staged RBAC paths successfully.' -ForegroundColor Green
Write-Host 'No git add . was used.' -ForegroundColor Green
Write-Host ''

git diff --cached --check
if ($LASTEXITCODE -ne 0) {
    throw 'git diff --cached --check failed.'
}

git status --short
git diff --cached --stat
