<?php

namespace App\Services\WhatsApp\Exceptions;

use RuntimeException;

class MetaApiException extends RuntimeException
{
    public function __construct(
        string $message,
        protected ?int $statusCode = null,
        protected ?string $errorCode = null,
        protected array $response = [],
    ) {
        parent::__construct($message, $statusCode ?? 0);
    }

    public function statusCode(): ?int
    {
        return $this->statusCode;
    }

    public function errorCode(): ?string
    {
        return $this->errorCode;
    }

    public function response(): array
    {
        return $this->response;
    }
}
