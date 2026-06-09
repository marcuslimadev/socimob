<?php

namespace App\Services;

use App\Models\AppSetting;

class AiAtendimentoProviderService
{
    public const SETTING_KEY = 'ai_atendimento_provider';
    public const OPENAI = 'openai';
    public const HUGGINGFACE = 'huggingface';
    public const SOCIMOB_AI = 'socimob_ai';

    public function resolve(?int $tenantId = null): string
    {
        $provider = AppSetting::getValue(self::SETTING_KEY, null, $tenantId);

        if ($provider === null || trim((string) $provider) === '') {
            return self::OPENAI;
        }

        return $this->normalize((string) $provider);
    }

    public function save(string $provider, ?int $tenantId = null): string
    {
        $normalized = $this->normalize($provider);
        AppSetting::setValue(self::SETTING_KEY, $normalized, $tenantId);

        return $normalized;
    }

    public function normalize(?string $provider): string
    {
        $provider = strtolower(trim((string) $provider));

        return match ($provider) {
            self::HUGGINGFACE, 'hf', 'hugging-face', 'hugginface', 'huggin-face' => self::HUGGINGFACE,
            self::SOCIMOB_AI, 'socimob-ai', 'socimobai', 'propria', 'propria-hospedada', 'self-hosted' => self::SOCIMOB_AI,
            default => self::OPENAI,
        };
    }

    public function options(): array
    {
        return [self::OPENAI, self::HUGGINGFACE, self::SOCIMOB_AI];
    }
}
