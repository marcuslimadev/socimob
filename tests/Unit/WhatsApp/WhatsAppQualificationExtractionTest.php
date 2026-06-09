<?php

namespace Tests\Unit\WhatsApp;

use App\Services\WhatsAppService;
use ReflectionClass;
use Tests\TestCase;

class WhatsAppQualificationExtractionTest extends TestCase
{
    public function test_it_understands_common_rental_move_timelines(): void
    {
        $service = $this->serviceWithoutConstructor();
        $method = $this->privateMethod('extractPurchaseTimelineFromText');

        $this->assertSame('19 de junho', $method->invoke($service, '19 de junho'));
        $this->assertSame('este mês', $method->invoke($service, 'Este mês'));
        $this->assertSame('até 15 dias', $method->invoke($service, '15 dias'));
        $this->assertSame('até dia 19', $method->invoke($service, 'até dia 19'));
    }

    public function test_it_keeps_combined_income_sources(): void
    {
        $service = $this->serviceWithoutConstructor();
        $method = $this->privateMethod('extractIncomeSourceFromText');

        $this->assertSame('CLT e autônomo', $method->invoke($service, 'clt e automono'));
        $this->assertSame('CLT e autônomo', $method->invoke($service, 'CLT e autônomo'));
    }

    private function serviceWithoutConstructor(): WhatsAppService
    {
        return (new ReflectionClass(WhatsAppService::class))->newInstanceWithoutConstructor();
    }

    private function privateMethod(string $name): \ReflectionMethod
    {
        $method = (new ReflectionClass(WhatsAppService::class))->getMethod($name);
        $method->setAccessible(true);

        return $method;
    }
}
