$ErrorActionPreference = 'Stop'

$files = @(
    'app/Console/Commands/ResetProfessionalDemoDatabase.php',
    'database/seeders/ProfessionalDemoDatabaseSeeder.php',
    'database/seeders/ProfessionalDemoSeeder.php',
    'database/seeders/Demo/ProfessionalCatalogSeeder.php',
    'database/seeders/Demo/ProfessionalUsersAndDistributionSeeder.php',
    'database/seeders/Demo/ProfessionalOperationsSeeder.php',
    'docs/PROFESSIONAL_DEMO_DATABASE_AR.md',
    'docs/PROFESSIONAL_DEMO_DATABASE_MANIFEST.txt',
    'scripts/professional-demo-database-git-add.ps1',
    'tests/Feature/ProfessionalDemoDatabaseTest.php'
)

git add -- $files

git diff --cached --check
git diff --cached --stat
git status --short
