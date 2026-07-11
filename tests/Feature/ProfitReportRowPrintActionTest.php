<?php

namespace Tests\Feature;

use App\Filament\Resources\ProfitReports\Tables\ProfitReportsTable;
use App\Models\ProfitReportEntry;
use Tests\TestCase;

class ProfitReportRowPrintActionTest extends TestCase
{
    public function test_it_resolves_each_profit_entry_to_its_original_print_route(): void
    {
        $invoiceEntry = new ProfitReportEntry();
        $invoiceEntry->forceFill([
            'entry_type' => 'invoice',
            'source_id' => 17,
        ]);

        $returnEntry = new ProfitReportEntry();
        $returnEntry->forceFill([
            'entry_type' => 'return',
            'source_id' => 23,
        ]);

        $this->assertSame(
            route('reports.sales-invoices.print', [
                'salesInvoice' => 17,
            ]),
            ProfitReportsTable::printUrlFor($invoiceEntry),
        );

        $this->assertSame(
            route('reports.sales-returns.print', [
                'salesReturn' => 23,
            ]),
            ProfitReportsTable::printUrlFor($returnEntry),
        );
    }

    public function test_it_does_not_build_a_print_url_for_an_unknown_entry_type(): void
    {
        $entry = new ProfitReportEntry();
        $entry->forceFill([
            'entry_type' => 'unknown',
            'source_id' => 1,
        ]);

        $this->assertNull(
            ProfitReportsTable::printUrlFor($entry),
        );
    }
}
