<?php

namespace App\Support\Api;

use Illuminate\Database\Eloquent\Model;

final readonly class MobileWriteResult
{
    public function __construct(
        public Model $record,
        public bool $replayed = false,
    ) {
    }
}
