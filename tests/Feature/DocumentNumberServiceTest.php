<?php

namespace Tests\Feature;

use App\Services\Support\DocumentNumberService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class DocumentNumberServiceTest extends TestCase
{
    use DatabaseTransactions;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_generates_sequential_numbers_for_same_type_and_day(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-04 10:00:00'));

        $service = app(DocumentNumberService::class);

        $this->assertSame('INV-20260704-00001', $service->next('sales_invoice', 'INV'));
        $this->assertSame('INV-20260704-00002', $service->next('sales_invoice', 'INV'));
        $this->assertSame('INV-20260704-00003', $service->next('sales_invoice', 'INV'));
    }

    public function test_sequences_are_separate_by_document_type(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-04 10:00:00'));

        $service = app(DocumentNumberService::class);

        $this->assertSame('INV-20260704-00001', $service->next('sales_invoice', 'INV'));
        $this->assertSame('PAY-20260704-00001', $service->next('customer_payment', 'PAY'));
        $this->assertSame('INV-20260704-00002', $service->next('sales_invoice', 'INV'));
    }

    public function test_sequences_restart_on_new_day(): void
    {
        $service = app(DocumentNumberService::class);

        Carbon::setTestNow(Carbon::parse('2026-07-04 10:00:00'));
        $this->assertSame('DCL-20260704-00001', $service->next('daily_closing', 'DCL'));

        Carbon::setTestNow(Carbon::parse('2026-07-05 10:00:00'));
        $this->assertSame('DCL-20260705-00001', $service->next('daily_closing', 'DCL'));
    }
}