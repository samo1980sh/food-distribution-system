<?php

namespace App\Support\Api;

use Illuminate\Http\JsonResponse;

final class ApiResponse
{
    /** @param array<string, mixed>|list<mixed>|null $data */
    public static function success(
        array|null $data = null,
        string $message = 'تمت العملية بنجاح.',
        int $status = 200,
        array $meta = [],
    ): JsonResponse {
        $payload = [
            'success' => true,
            'message' => $message,
            'data' => $data,
        ];

        if ($meta !== []) {
            $payload['meta'] = $meta;
        }

        return response()->json($payload, $status);
    }

    /** @param array<string, mixed>|null $errors */
    public static function error(
        string $message,
        string $code,
        int $status,
        ?array $errors = null,
    ): JsonResponse {
        $payload = [
            'success' => false,
            'message' => $message,
            'code' => $code,
        ];

        if ($errors !== null && $errors !== []) {
            $payload['errors'] = $errors;
        }

        return response()->json($payload, $status);
    }
}
