<?php

namespace App\Services\Support;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class DocumentNumberService
{
    public function next(string $type, string $prefix): string
    {
        $now = now();
        $date = $now->toDateString();
        $datePart = $now->format('Ymd');

        $number = DB::transaction(function () use ($type, $date): int {
            $sequence = DB::table('document_sequences')
                ->where('document_type', $type)
                ->where('sequence_date', $date)
                ->lockForUpdate()
                ->first();

            if (! $sequence) {
                $insertException = null;

                try {
                    DB::table('document_sequences')->insert([
                        'document_type' => $type,
                        'sequence_date' => $date,
                        'last_number' => 0,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                } catch (QueryException $exception) {
                    $insertException = $exception;
                    // Another request created today's sequence first; lock it below.
                }

                $sequence = DB::table('document_sequences')
                    ->where('document_type', $type)
                    ->where('sequence_date', $date)
                    ->lockForUpdate()
                    ->first();

                if (! $sequence && $insertException) {
                    throw $insertException;
                }
            }

            $nextNumber = ((int) $sequence->last_number) + 1;

            DB::table('document_sequences')
                ->where('id', $sequence->id)
                ->update([
                    'last_number' => $nextNumber,
                    'updated_at' => now(),
                ]);

            return $nextNumber;
        });

        return $prefix.'-'.$datePart.'-'.str_pad((string) $number, 5, '0', STR_PAD_LEFT);
    }
}
