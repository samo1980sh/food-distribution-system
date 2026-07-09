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
        Carbon::setTestNow(Carbon::parse('2037-01-01 10:00:00'));

        $service = app(DocumentNumberService::class);

        $this->assertSame('TST-20370101-00001', $service->next('test_document_number', 'TST'));
        $this->assertSame('TST-20370101-00002', $service->next('test_document_number', 'TST'));
        $this->assertSame('TST-20370101-00003', $service->next('test_document_number', 'TST'));
    }

    public function test_sequences_are_separate_by_document_type(): void
    {
        Carbon::setTestNow(Carbon::parse('2037-01-02 10:00:00'));

        $service = app(DocumentNumberService::class);

        $this->assertSame('TSA-20370102-00001', $service->next('test_document_type_a', 'TSA'));
        $this->assertSame('TSB-20370102-00001', $service->next('test_document_type_b', 'TSB'));
        $this->assertSame('TSA-20370102-00002', $service->next('test_document_type_a', 'TSA'));
    }

    public function test_sequences_restart_on_new_day(): void
    {
        $service = app(DocumentNumberService::class);

        Carbon::setTestNow(Carbon::parse('2037-01-03 10:00:00'));
        $this->assertSame('TSD-20370103-00001', $service->next('test_daily_document', 'TSD'));

        Carbon::setTestNow(Carbon::parse('2037-01-04 10:00:00'));
        $this->assertSame('TSD-20370104-00001', $service->next('test_daily_document', 'TSD'));
    }
}
