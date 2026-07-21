$ErrorActionPreference = "Stop"

$project = Split-Path -Parent $PSScriptRoot
Set-Location $project

php artisan optimize:clear
php artisan test tests/Feature/OperationalInventoryImpactTest.php tests/Feature/VehicleLoadExpenseFilamentWorkflowTest.php
php artisan test
git diff --check
git status --short
