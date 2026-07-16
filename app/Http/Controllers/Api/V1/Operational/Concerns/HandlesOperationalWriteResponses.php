<?php

namespace App\Http\Controllers\Api\V1\Operational\Concerns;

use App\Exceptions\Api\OperationalApiException;
use App\Support\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use RuntimeException;

trait HandlesOperationalWriteResponses
{
    /** @param callable(): JsonResponse $callback */
    protected function handleOperationalWrite(callable $callback): JsonResponse
    {
        try {
            return $callback();
        } catch (OperationalApiException $exception) {
            return ApiResponse::error(
                $exception->getMessage(),
                $exception->apiCode,
                $exception->status,
            );
        } catch (RuntimeException $exception) {
            if ($exception::class !== RuntimeException::class) {
                throw $exception;
            }

            return ApiResponse::error(
                $exception->getMessage(),
                'business_rule_violation',
                409,
            );
        }
    }
}
