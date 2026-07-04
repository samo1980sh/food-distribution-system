<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class SyncDocumentSequences extends Command
{
    protected $signature = 'documents:sync-sequences {--apply : Persist the calculated sequence values}';

    protected $description = 'Synchronize document_sequences with the highest existing document numbers.';

    private array $sources = [
        [
            'document_type' => 'stock_movement',
            'prefix' => 'STM',
            'table' => 'stock_movements',
            'column' => 'movement_number',
        ],
        [
            'document_type' => 'vehicle_load',
            'prefix' => 'VLD',
            'table' => 'vehicle_loads',
            'column' => 'load_number',
        ],
        [
            'document_type' => 'sales_invoice',
            'prefix' => 'INV',
            'table' => 'sales_invoices',
            'column' => 'invoice_number',
        ],
        [
            'document_type' => 'customer_payment',
            'prefix' => 'PAY',
            'table' => 'customer_payments',
            'column' => 'payment_number',
        ],
        [
            'document_type' => 'sales_return',
            'prefix' => 'SRT',
            'table' => 'sales_returns',
            'column' => 'return_number',
        ],
        [
            'document_type' => 'daily_closing',
            'prefix' => 'DCL',
            'table' => 'daily_closings',
            'column' => 'closing_number',
        ],
    ];

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $maxExistingNumbers = $this->collectMaxExistingNumbers();

        if ($maxExistingNumbers === []) {
            $this->info('No document numbers were found.');

            return self::SUCCESS;
        }

        $rows = [];

        DB::transaction(function () use ($apply, $maxExistingNumbers, &$rows): void {
            foreach ($maxExistingNumbers as $key => $maxExistingNumber) {
                [$documentType, $date] = explode('|', $key);

                $currentSequenceNumber = (int) DB::table('document_sequences')
                    ->where('document_type', $documentType)
                    ->where('sequence_date', $date)
                    ->lockForUpdate()
                    ->value('last_number');

                $targetNumber = max($currentSequenceNumber, $maxExistingNumber);
                $status = $targetNumber > $currentSequenceNumber
                    ? ($apply ? 'updated' : 'needs update')
                    : 'ok';

                if ($apply && $targetNumber > $currentSequenceNumber) {
                    $existingSequence = DB::table('document_sequences')
                        ->where('document_type', $documentType)
                        ->where('sequence_date', $date)
                        ->first();

                    if ($existingSequence) {
                        DB::table('document_sequences')
                            ->where('id', $existingSequence->id)
                            ->update([
                                'last_number' => $targetNumber,
                                'updated_at' => now(),
                            ]);
                    } else {
                        DB::table('document_sequences')->insert([
                            'document_type' => $documentType,
                            'sequence_date' => $date,
                            'last_number' => $targetNumber,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }
                }

                $rows[] = [
                    $documentType,
                    $date,
                    $maxExistingNumber,
                    $currentSequenceNumber,
                    $targetNumber,
                    $status,
                ];
            }
        });

        $this->table(
            ['Document type', 'Date', 'Existing max', 'Current sequence', 'Target', 'Status'],
            $rows,
        );

        $this->line($apply
            ? 'document_sequences synchronized.'
            : 'Dry run only. Re-run with --apply to persist changes.'
        );

        return self::SUCCESS;
    }

    private function collectMaxExistingNumbers(): array
    {
        $maxExistingNumbers = [];

        foreach ($this->sources as $source) {
            DB::table($source['table'])
                ->whereNotNull($source['column'])
                ->orderBy($source['column'])
                ->pluck($source['column'])
                ->each(function (string $documentNumber) use ($source, &$maxExistingNumbers): void {
                    $parts = $this->parseDocumentNumber($documentNumber, $source['prefix']);

                    if (! $parts) {
                        return;
                    }

                    [$date, $number] = $parts;
                    $key = $source['document_type'].'|'.$date;

                    $maxExistingNumbers[$key] = max(
                        $maxExistingNumbers[$key] ?? 0,
                        $number,
                    );
                });
        }

        ksort($maxExistingNumbers);

        return $maxExistingNumbers;
    }

    private function parseDocumentNumber(string $documentNumber, string $prefix): ?array
    {
        $pattern = '/^'.preg_quote($prefix, '/').'-(\d{8})-(\d+)$/';

        if (! preg_match($pattern, $documentNumber, $matches)) {
            return null;
        }

        return [
            Carbon::createFromFormat('Ymd', $matches[1])->toDateString(),
            (int) $matches[2],
        ];
    }
}