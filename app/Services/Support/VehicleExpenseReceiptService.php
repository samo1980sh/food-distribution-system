<?php

namespace App\Services\Support;

use App\Models\VehicleExpense;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class VehicleExpenseReceiptService
{
    public const DISK = 'local';

    public const DIRECTORY = 'vehicle-expense-receipts';

    public function store(?UploadedFile $receipt): ?string
    {
        return $receipt?->store(self::DIRECTORY, self::DISK);
    }

    public function delete(?string $path): void
    {
        if (filled($path)) {
            Storage::disk(self::DISK)->delete($path);
        }
    }

    public function response(VehicleExpense $expense): StreamedResponse
    {
        $path = $expense->receipt_path;

        abort_if(
            blank($path) || ! Storage::disk(self::DISK)->exists($path),
            404,
        );

        $extension = pathinfo((string) $path, PATHINFO_EXTENSION);
        $filename = 'vehicle-expense-receipt-'.$expense->getKey()
            .($extension !== '' ? '.'.$extension : '');

        return Storage::disk(self::DISK)->response(
            (string) $path,
            $filename,
            [
                'Cache-Control' => 'private, no-store, max-age=0',
                'Pragma' => 'no-cache',
                'X-Content-Type-Options' => 'nosniff',
            ],
            'inline',
        );
    }
}
