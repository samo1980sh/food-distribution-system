$ErrorActionPreference = 'Stop'

$paths = @(
    '.env.example',
    'app/Enums/PermissionName.php',
    'app/Http/Controllers/Api/V1',
    'app/Http/Middleware/AddMobileApiHeaders.php',
    'app/Http/Middleware/EnsureMobileApiAccess.php',
    'app/Http/Middleware/TouchMobileApiToken.php',
    'app/Http/Requests/Api/V1/Auth/LoginRequest.php',
    'app/Http/Resources/Api/V1',
    'app/Models/User.php',
    'app/Providers/MobileApiServiceProvider.php',
    'app/Services/Api',
    'app/Support/Api',
    'app/Support/Authorization/RolePermissionMap.php',
    'bootstrap/app.php',
    'bootstrap/providers.php',
    'config/mobile_api.php',
    'database/migrations/2026_07_15_180000_add_mobile_metadata_to_personal_access_tokens.php',
    'docs/api',
    'docs/rbac/INSTALL_AR.md',
    'docs/rbac/MANIFEST.txt',
    'docs/rbac/ROLE_MATRIX_AR.md',
    'routes/api.php',
    'routes/console.php',
    'scripts/mobile-api-git-add.ps1',
    'tests/Feature/Api/MobileApiFoundationTest.php',
    'tests/Feature/RbacFoundationTest.php'
)

foreach ($path in $paths) {
    if (-not (Test-Path $path)) {
        throw "Required path not found: $path"
    }

    git add -- $path
}

Write-Host ''
Write-Host 'Staged Mobile API Foundation paths successfully.' -ForegroundColor Green
Write-Host 'No git add . was used.' -ForegroundColor Green
Write-Host ''

git status --short
