<?php

namespace Tests\Unit;

use App\Http\Controllers\ConversasController;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class ConversasMediaProxyUrlTest extends TestCase
{
    // IDs sinteticos montados em tempo de execucao: um literal AC + 32 hex dispara o secret scanning do GitHub.
    private static function sid(): string
    {
        return 'AC' . str_repeat('0123456789abcdef', 2);
    }

    private static function msg(): string
    {
        return 'MM' . str_repeat('0123456789abcdef', 2);
    }

    private static function media(): string
    {
        return 'ME' . str_repeat('0123456789abcdef', 2);
    }

    private function isAllowed(string $url): bool
    {
        $controller = (new \ReflectionClass(ConversasController::class))->newInstanceWithoutConstructor();
        $method = new ReflectionMethod($controller, 'isAllowedTwilioMediaUrl');
        $method->setAccessible(true);

        return $method->invoke($controller, $url);
    }

    public static function allowedUrls(): array
    {
        $path = '/2010-04-01/Accounts/' . self::sid() . '/Messages/' . self::msg() . '/Media/' . self::media();

        return [
            'media da mensagem' => ['https://api.twilio.com' . $path],
            'media com extensao' => ['https://api.twilio.com' . $path . '.jpg'],
            'cdn do twilio' => ['https://media.twiliocdn.com/ACxxx/abc123'],
        ];
    }

    public static function blockedUrls(): array
    {
        $path = '/2010-04-01/Accounts/' . self::sid() . '/Messages/' . self::msg() . '/Media/' . self::media();

        return [
            'host malicioso com twilio no query' => ['https://evil.com/x?ref=api.twilio.com'],
            'subdominio enganoso' => ['https://api.twilio.com.evil.com' . $path],
            'http sem tls' => ['http://api.twilio.com' . $path],
            'userinfo' => ['https://api.twilio.com@evil.com' . $path],
            'porta explicita' => ['https://api.twilio.com:8443' . $path],
            'listagem de mensagens' => ['https://api.twilio.com/2010-04-01/Accounts/' . self::sid() . '/Messages.json'],
            'path traversal' => ['https://api.twilio.com/2010-04-01/Accounts/' . self::sid() . '/Messages/' . self::msg() . '/Media/../../Messages.json'],
            'cdn com traversal' => ['https://media.twiliocdn.com/../secret'],
            'vazio' => [''],
        ];
    }

    #[DataProvider('allowedUrls')]
    public function test_aceita_urls_de_midia_do_twilio(string $url): void
    {
        $this->assertTrue($this->isAllowed($url));
    }

    #[DataProvider('blockedUrls')]
    public function test_bloqueia_urls_fora_do_padrao(string $url): void
    {
        $this->assertFalse($this->isAllowed($url));
    }
}
