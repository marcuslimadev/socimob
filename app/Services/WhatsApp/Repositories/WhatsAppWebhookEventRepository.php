<?php

namespace App\Services\WhatsApp\Repositories;

use App\Models\WhatsApp\WhatsAppWebhookEvent;
use Illuminate\Database\QueryException;

class WhatsAppWebhookEventRepository
{
    public function createOrSkip(array $attributes): ?WhatsAppWebhookEvent
    {
        try {
            return WhatsAppWebhookEvent::query()->create($attributes);
        } catch (QueryException $exception) {
            $message = $exception->getMessage();

            if (str_contains(strtolower($message), 'duplicate') || str_contains(strtolower($message), 'unique')) {
                return null;
            }

            throw $exception;
        }
    }
}
