<?php

namespace App\Exceptions;

use RuntimeException;

class PublicBookingException extends RuntimeException
{
    private int $statusCode;
    private array $errors;

    public function __construct(string $message, int $statusCode = 400, array $errors = [], ?\Throwable $previous = null)
    {
        parent::__construct($message, $statusCode, $previous);
        $this->statusCode = $statusCode;
        $this->errors = $errors;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }
}
