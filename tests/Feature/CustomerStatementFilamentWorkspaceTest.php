<?php

namespace Tests\Feature;

use Tests\TestCase;

class CustomerStatementFilamentWorkspaceTest extends TestCase
{
    public function test_customer_statement_uses_a_focused_filament_account_workspace(): void
    {
        $view = $this->readProjectFile('resources/views/filament/pages/customer-statement-report.blade.php');

        foreach ([
            'customer-statement-shell',
            'statement-query-panel',
            'statement-customer-header',
            'statement-summary-grid',
            'statement-secondary-metrics',
            'statement-ledger-panel',
            'statement-empty-state',
            'خيارات كشف الحساب',
            'بيانات العميل',
            'فترة كشف الحساب',
            'حركة الحساب',
            'الفواتير والتحصيلات والمرتجعات مرتبة زمنيًا',
        ] as $fragment) {
            $this->assertStringContainsString($fragment, $view);
        }

        foreach ([
            'statement-customer-grid',
            'statement-totals-grid',
            'statement-panel-title',
            'statement-total-closing',
        ] as $legacyFragment) {
            $this->assertStringNotContainsString($legacyFragment, $view);
        }

        $this->assertSame(4, substr_count($view, 'class="statement-summary-card'));
        $this->assertSame(6, substr_count($view, 'class="statement-secondary-item"'));
        $this->assertStringContainsString('@media (max-width: 900px)', $view);
        $this->assertStringContainsString('@media (max-width: 640px)', $view);
        $this->assertStringContainsString('.dark .customer-statement-shell', $view);
    }

    public function test_customer_statement_keeps_existing_form_generation_printing_and_permissions(): void
    {
        $page = $this->readProjectFile('app/Filament/Pages/CustomerStatementReport.php');
        $controller = $this->readProjectFile('app/Http/Controllers/Reports/CustomerStatementPrintController.php');

        foreach ([
            "PermissionName::REPORT_CUSTOMER_STATEMENT->value",
            "protected static ?string \$slug = 'customer-statement'",
            "protected string \$view = 'filament.pages.customer-statement-report'",
            "Select::make('customer_id')",
            "DatePicker::make('from')",
            "DatePicker::make('until')",
            "->livewireSubmitHandler('generate')",
            "Action::make('generate')",
            "Action::make('print')",
            "route(\n                        'reports.customer-statement.print'",
            "app(CustomerStatementService::class)",
            "customerId: (int) \$state['customer_id']",
            "from: \$from",
            "until: \$until",
            "\$until < \$from",
        ] as $fragment) {
            $this->assertStringContainsString($fragment, $page);
        }

        foreach ([
            "Auth::check()",
            "PermissionName::REPORT_CUSTOMER_STATEMENT->value",
            "'customer_id' => [",
            "'after_or_equal:from'",
            "app(CustomerStatementService::class)",
            "view('reports.customer-statements.print'",
            "'generatedBy' => Auth::user()?->name",
        ] as $fragment) {
            $this->assertStringContainsString($fragment, $controller);
        }
    }

    public function test_customer_statement_keeps_customer_totals_transactions_and_running_balance_calculations(): void
    {
        $view = $this->readProjectFile('resources/views/filament/pages/customer-statement-report.blade.php');
        $service = $this->readProjectFile('app/Services/Reports/CustomerStatementService.php');

        foreach ([
            "\$customer['code']",
            "\$customer['name']",
            "\$customer['owner_name']",
            "\$customer['phone']",
            "\$customer['mobile']",
            "\$customer['area']",
            "\$customer['route']",
            "\$customer['address']",
            "\$customer['credit_limit']",
            "\$totals['opening_balance']",
            "\$totals['period_debit']",
            "\$totals['period_credit']",
            "\$totals['closing_balance']",
            "\$totals['sales_total']",
            "\$totals['invoice_cash_total']",
            "\$totals['payments_total']",
            "\$totals['returns_total']",
            "\$totals['transaction_count']",
            "\$transaction['date']",
            "\$transaction['type']",
            "\$transaction['type_label']",
            "\$transaction['document_number']",
            "\$transaction['description']",
            "\$transaction['notes']",
            "\$transaction['debit']",
            "\$transaction['credit']",
            "\$transaction['balance']",
            'الرصيد الافتتاحي قبل بداية الفترة',
            'إجمالي الفترة والرصيد الختامي',
        ] as $fragment) {
            $this->assertStringContainsString($fragment, $view);
        }

        foreach ([
            "->where('status', 'confirmed')",
            "->whereDate('invoice_date', '>=', \$from)",
            "->whereDate('invoice_date', '<=', \$until)",
            "->whereDate('payment_date', '>=', \$from)",
            "->whereDate('payment_date', '<=', \$until)",
            "->whereDate('return_date', '>=', \$from)",
            "->whereDate('return_date', '<=', \$until)",
            "\$first['sort_order']",
            "\$second['sort_order']",
            "openingBalance: \$openingBalance",
            "\$periodDebit = (float) \$transactions->sum('debit')",
            "\$periodCredit = (float) \$transactions->sum('credit')",
            "'closing_balance' => \$openingBalance",
            "+ \$periodDebit",
            "- \$periodCredit",
            "\$runningBalance += (float) \$transaction['debit']",
            "\$runningBalance -= (float) \$transaction['credit']",
            "\$transaction['balance'] = \$runningBalance",
            "'transaction_count' => \$transactions->count()",
        ] as $fragment) {
            $this->assertStringContainsString($fragment, $service);
        }
    }

    private function readProjectFile(string $path): string
    {
        $contents = file_get_contents(base_path($path));

        $this->assertNotFalse($contents, "Unable to read [{$path}].");

        return $contents;
    }
}
