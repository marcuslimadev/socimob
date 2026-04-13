<?php

namespace App\Services\WhatsApp\Exceptions;

class MetaRateLimitException extends MetaApiException
{
    public function __construct(
        string $message,
        ?int $statusCode = null,
        ?string $errorCode = null,
        array $response = [],
        protected ?int $retryAfterSeconds = null,
    ) {
        parent::__construct($message, $statusCode, $errorCode, $response);
    }

    public function retryAfterSeconds(): ?int
    {
        return $this->retryAfterSeconds;
    }
}
