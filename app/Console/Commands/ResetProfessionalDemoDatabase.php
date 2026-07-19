<?php

namespace App\Console\Commands;

use Database\Seeders\ProfessionalDemoDatabaseSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Throwable;

class ResetProfessionalDemoDatabase extends Command
{
    protected $signature = 'demo:reset
        {--check : Validate the environment and display the reset plan without changing data}
        {--apply : Rebuild the database and load the professional demo dataset}
        {--force : Skip the interactive confirmation in non-production environments}';

    protected $description = 'Safely rebuild the local database and load professional demo data for the admin panel and Flutter app.';

    public function handle(): int
    {
        $check = (bool) $this->option('check');
        $apply = (bool) $this->option('apply');

        if ($check === $apply) {
            $this->error('استخدم خياراً واحداً فقط: --check أو --apply.');

            return self::INVALID;
        }

        if (! $this->environmentIsSafe()) {
            return self::FAILURE;
        }

        $this->displayEnvironment();
        $this->displayCurrentCounts();
        $this->displayTargetSummary();

        if ($check) {
            $this->newLine();
            $this->info('الفحص ناجح. لم يتم حذف أو تعديل أي بيانات.');
            $this->line('للتنفيذ: php artisan demo:reset --apply');

            return self::SUCCESS;
        }

        if (! $this->option('force') && ! $this->confirm(
            'سيتم حذف جميع جداول قاعدة البيانات الحالية وإعادة بنائها. هل أنت متأكد؟',
            false,
        )) {
            $this->warn('تم إلغاء العملية. لم يتم تغيير أي بيانات.');

            return self::SUCCESS;
        }

        try {
            $this->components->task('إعادة بناء قاعدة البيانات', function (): bool {
                $exitCode = Artisan::call('migrate:fresh', [
                    '--force' => true,
                ]);

                if ($exitCode !== self::SUCCESS) {
                    throw new RuntimeException(trim(Artisan::output()) ?: 'فشل تنفيذ migrate:fresh.');
                }

                return true;
            });

            $this->components->task('تحميل البيانات التجريبية الاحترافية', function (): bool {
                $exitCode = Artisan::call('db:seed', [
                    '--class' => ProfessionalDemoDatabaseSeeder::class,
                    '--force' => true,
                ]);

                if ($exitCode !== self::SUCCESS) {
                    throw new RuntimeException(trim(Artisan::output()) ?: 'فشل تحميل البيانات التجريبية.');
                }

                return true;
            });

            Artisan::call('documents:sync-sequences', [
                '--apply' => true,
            ]);
            Artisan::call('permission:cache-reset');
            Artisan::call('optimize:clear');
        } catch (Throwable $exception) {
            $this->newLine();
            $this->error('فشلت إعادة التهيئة: '.$exception->getMessage());

            return self::FAILURE;
        }

        $this->newLine();
        $this->info('تمت إعادة تهيئة قاعدة البيانات وتحميل البيانات التجريبية بنجاح.');
        $this->displayVerificationCounts();
        $this->displayDemoAccounts();

        return self::SUCCESS;
    }

    private function environmentIsSafe(): bool
    {
        if (app()->environment('production')) {
            $this->error('تم حظر الأمر لأن APP_ENV=production.');

            return false;
        }

        if (! app()->environment(['local', 'testing'])) {
            $this->error('يعمل هذا الأمر فقط عندما تكون APP_ENV بقيمة local أو testing.');

            return false;
        }

        $connection = (string) config('database.default');
        $database = trim((string) config("database.connections.{$connection}.database"));

        if ($database === '') {
            $this->error('اسم قاعدة البيانات غير محدد.');

            return false;
        }

        return true;
    }

    private function displayEnvironment(): void
    {
        $connection = (string) config('database.default');
        $config = (array) config("database.connections.{$connection}", []);

        $this->table(
            ['Environment', 'Connection', 'Host', 'Database'],
            [[
                app()->environment(),
                $connection,
                (string) ($config['host'] ?? '—'),
                (string) ($config['database'] ?? '—'),
            ]],
        );
    }

    private function displayCurrentCounts(): void
    {
        $tables = [
            'users',
            'customers',
            'products',
            'stock_balances',
            'sales_invoices',
            'customer_payments',
            'sales_returns',
            'vehicle_loads',
            'vehicle_expenses',
            'daily_closings',
        ];

        $rows = [];

        foreach ($tables as $table) {
            $rows[] = [
                $table,
                Schema::hasTable($table) ? DB::table($table)->count() : 0,
            ];
        }

        $this->newLine();
        $this->line('<fg=yellow>البيانات الحالية التي سيتم حذفها عند --apply:</>');
        $this->table(['Table', 'Current rows'], $rows);
    }

    private function displayTargetSummary(): void
    {
        $this->newLine();
        $this->line('<fg=cyan>محتوى البيئة التجريبية الجديدة:</>');
        $this->table(
            ['Domain', 'Target'],
            [
                ['Users', '10 accounts covering all roles and 3 Flutter scenarios'],
                ['Distribution', '4 areas, 5 routes, 4 vehicles, 7 warehouses'],
                ['Customers', '20 customers with cash, partial, credit and overdue cases'],
                ['Catalog', '15 products across 6 categories and 5 units'],
                ['Inventory', 'Opening balances, vehicle stock, expiry-risk batches and weighted costs'],
                ['Operations', 'Loads, invoices, collections, returns, expenses and daily closings'],
                ['Reports', 'Data for sales, profit, overdue, top customers, route performance and expiry risk'],
            ],
        );
    }

    private function displayVerificationCounts(): void
    {
        $this->table(
            ['Metric', 'Rows'],
            [
                ['Users', DB::table('users')->count()],
                ['Employees', DB::table('employees')->count()],
                ['Customers', DB::table('customers')->count()],
                ['Products', DB::table('products')->count()],
                ['Stock balances', DB::table('stock_balances')->count()],
                ['Stock movements', DB::table('stock_movements')->count()],
                ['Confirmed invoices', DB::table('sales_invoices')->where('status', 'confirmed')->count()],
                ['Confirmed payments', DB::table('customer_payments')->where('status', 'confirmed')->count()],
                ['Confirmed returns', DB::table('sales_returns')->where('status', 'confirmed')->count()],
                ['Approved loads', DB::table('vehicle_loads')->where('status', 'approved')->count()],
                ['Approved expenses', DB::table('vehicle_expenses')->where('status', 'approved')->count()],
                ['Confirmed closings', DB::table('daily_closings')->where('status', 'confirmed')->count()],
            ],
        );
    }

    private function displayDemoAccounts(): void
    {
        $this->newLine();
        $this->line('<fg=green>كلمة المرور المشتركة لجميع الحسابات:</> Demo@2026');
        $this->table(
            ['Scenario', 'Email', 'Access'],
            [
                ['Admin', 'admin@demo.local', 'Full admin panel'],
                ['Manager', 'manager@demo.local', 'Management and reports'],
                ['Supervisor', 'supervisor@demo.local', 'Scoped distribution supervision'],
                ['Warehouse', 'warehouse@demo.local', 'Warehouse operations'],
                ['Accountant', 'accountant@demo.local', 'Financial operations and reports'],
                ['Flutter sales', 'sales@demo.local', 'Sales representative only'],
                ['Flutter driver', 'driver@demo.local', 'Driver only'],
                ['Flutter dual', 'field.team@demo.local', 'Driver + sales representative'],
            ],
        );
    }
}
