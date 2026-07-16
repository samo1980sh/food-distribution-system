$ErrorActionPreference = "Stop"

$paths = @(
    ".env.example",
    "app/Exceptions/Api/OperationalApiException.php",
    "app/Http/Controllers/Api/V1/Operational/Concerns/HandlesOperationalWriteResponses.php",
    "app/Http/Controllers/Api/V1/Operational/CustomerPaymentController.php",
    "app/Http/Controllers/Api/V1/Operational/DailyClosingController.php",
    "app/Http/Controllers/Api/V1/Operational/OperationalBootstrapController.php",
    "app/Http/Controllers/Api/V1/Operational/SalesInvoiceController.php",
    "app/Http/Controllers/Api/V1/Operational/SalesReturnController.php",
    "app/Http/Controllers/Api/V1/Operational/VehicleExpenseController.php",
    "app/Http/Requests/Api/V1/Operational/CustomerPaymentWriteRequest.php",
    "app/Http/Requests/Api/V1/Operational/DailyClosingWriteRequest.php",
    "app/Http/Requests/Api/V1/Operational/OperationalWriteRequest.php",
    "app/Http/Requests/Api/V1/Operational/SalesInvoiceWriteRequest.php",
    "app/Http/Requests/Api/V1/Operational/SalesReturnWriteRequest.php",
    "app/Http/Requests/Api/V1/Operational/VehicleExpenseRejectRequest.php",
    "app/Http/Requests/Api/V1/Operational/VehicleExpenseWriteRequest.php",
    "app/Http/Resources/Api/V1/Operational/CustomerPaymentResource.php",
    "app/Http/Resources/Api/V1/Operational/DailyClosingResource.php",
    "app/Http/Resources/Api/V1/Operational/SalesInvoiceResource.php",
    "app/Http/Resources/Api/V1/Operational/SalesReturnResource.php",
    "app/Http/Resources/Api/V1/Operational/VehicleExpenseResource.php",
    "app/Models/CustomerPayment.php",
    "app/Models/DailyClosing.php",
    "app/Models/SalesInvoice.php",
    "app/Models/SalesReturn.php",
    "app/Models/VehicleExpense.php",
    "app/Services/Api/MobileOperationalWriteService.php",
    "app/Support/Api/MobileWriteResult.php",
    "config/mobile_api.php",
    "database/migrations/2026_07_16_120000_add_mobile_client_references_to_operational_tables.php",
    "docs/api/MOBILE_OPERATIONAL_READ_API_AR.md",
    "docs/api/MOBILE_OPERATIONAL_WRITE_API_PHASE1_AR.md",
    "docs/api/MOBILE_OPERATIONAL_WRITE_API_PHASE1_INSTALL_AR.md",
    "docs/api/MOBILE_OPERATIONAL_WRITE_API_PHASE1_MANIFEST.txt",
    "docs/api/openapi.yaml",
    "routes/api.php",
    "scripts/mobile-operational-write-api-git-add.ps1",
    "tests/Feature/Api/MobileOperationalWriteApiTest.php"
)

foreach ($path in $paths) {
    git add -- $path
}

Write-Host "Staged Mobile Operational Write API Phase 1 paths successfully."
Write-Host "No git add . was used."
git status --short
