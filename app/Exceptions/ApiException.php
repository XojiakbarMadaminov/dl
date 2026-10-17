<?php

namespace App\Exceptions;

use Exception;
use Throwable;

class ApiException extends Exception
{
    /**
     * @param  array<string, array<int, string>|string>  $fields
     */
    public function __construct(
        string $message,
        protected string $errorCode = 'BAD_REQUEST',
        protected int $statusCode = 400,
        protected array $fields = [],
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $statusCode, $previous);
    }

    public function getErrorCode(): string
    {
        return $this->errorCode;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * @return array<string, array<int, string>|string>
     */
    public function getFields(): array
    {
        return $this->fields;
    }
}
