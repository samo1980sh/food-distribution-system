$ErrorActionPreference = "Stop"

$paths = @(
    "app/Http/Controllers/Api/V1/Operational",
    "app/Http/Requests/Api/V1/Operational",
    "app/Http/Resources/Api/V1/Operational",
    "app/Support/Api/ApiResponse.php",
    "app/Services/Api/MobileBootstrapService.php",`r`n    "app/Services/Api/MobileOperationalService.php",`r`n    "app/Support/Authorization/RolePermissionMap.php",`r`n    "config/mobile_api.php",`r`n    "routes/api.php",
    "docs/api/MOBILE_API_FOUNDATION_AR.md",
    "docs/api/MOBILE_API_INSTALL_AR.md",
    "docs/api/MOBILE_OPERATIONAL_READ_API_AR.md",
    "docs/api/MOBILE_OPERATIONAL_READ_API_INSTALL_AR.md",
    "docs/api/MOBILE_OPERATIONAL_READ_API_MANIFEST.txt",
    "docs/api/openapi.yaml",
    "docs/rbac/ROLE_MATRIX_AR.md",
    "scripts/mobile-operational-read-api-git-add.ps1",
    "tests/Feature/Api/MobileOperationalReadApiTest.php",
    "tests/Feature/RbacFoundationTest.php"
)

foreach ($path in $paths) {
    git add -- $path
}

Write-Host "Staged Mobile Operational Read API paths successfully."
Write-Host "No git add . was used."
git status --short
