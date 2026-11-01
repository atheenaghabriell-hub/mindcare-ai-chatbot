<?php

namespace App\Services\AI;

use RuntimeException;
use Throwable;

class GeminiChatException extends RuntimeException
{
    public function __construct(
        public readonly string $failureCode,
        string $message,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, previous: $previous);
    }
}
