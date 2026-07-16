<?php

namespace App\Exceptions\Api;

use RuntimeException;

class OperationalApiException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly string $apiCode = 'operational_conflict',
        public readonly int $status = 409,
    ) {
        parent::__construct($message);
    }
}
