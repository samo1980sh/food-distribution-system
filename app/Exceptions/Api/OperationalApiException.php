<?php

namespace App\Exceptions\Api;

use RuntimeException;

class OperationalApiException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly string $apiCode = 'operational_conflict',
        public readonly int $status = 409,
        public readonly ?array $errors = null,
    ) {
        parent::__construct($message);
    }
}
