<?php

namespace Tests\Unit;

use App\Services\OpenAIService;
use ReflectionMethod;
use Tests\TestCase;

class OpenAIServiceTest extends TestCase
{
    public function test_decode_json_object_accepts_code_fenced_json(): void
    {
        $method = new ReflectionMethod(OpenAIService::class, 'decodeJsonObject');
        $method->setAccessible(true);

        $decoded = $method->invoke(new OpenAIService(), "```json\n{\"budget_max\":500000,\"quartos\":3}\n```");

        $this->assertSame(500000, $decoded['budget_max']);
        $this->assertSame(3, $decoded['quartos']);
    }

    public function test_decode_json_object_accepts_json_inside_text(): void
    {
        $method = new ReflectionMethod(OpenAIService::class, 'decodeJsonObject');
        $method->setAccessible(true);

        $decoded = $method->invoke(new OpenAIService(), "Dados extraídos:\n{\"localizacao\":\"Savassi\"}\nObrigado");

        $this->assertSame('Savassi', $decoded['localizacao']);
    }
}
