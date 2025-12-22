<?php

namespace App\Services;

use App\Models\AppSetting;

class PaymentGatewayResolver
{
    private const SUPPORTED = [
        'mercado_pago' => [
            'label' => 'Mercado Pago',
            'implemented' => true,
        ],
        'stripe' => [
            'label' => 'Stripe',
            'implemented' => false,
        ],
        'abacatepay' => [
            'label' => 'Abacatepay',
            'implemented' => false,
        ],
    ];

    public function __construct(private MercadoPagoService $mercadoPagoService)
    {
    }

    public function getActiveGateway(): string
    {
        $saved = $this->normalizeGateway(AppSetting::getValue('billing_gateway'));

        return $saved ?? 'mercado_pago';
    }

    public function getGatewayLabel(?string $gateway = null): string
    {
        $key = $this->normalizeGateway($gateway) ?? 'mercado_pago';

        return self::SUPPORTED[$key]['label'] ?? 'Mercado Pago';
    }

    public function isImplemented(string $gateway): bool
    {
        $key = $this->normalizeGateway($gateway);

        return $key !== null && (self::SUPPORTED[$key]['implemented'] ?? false);
    }

    public function availableGateways(): array
    {
        return collect(self::SUPPORTED)
            ->map(function ($config, $key) {
                return [
                    'key' => $key,
                    'label' => $config['label'],
                    'implemented' => $config['implemented'],
                ];
            })
            ->values()
            ->all();
    }

    public function normalizeGateway(?string $gateway): ?string
    {
        if (!$gateway) {
            return null;
        }

        $key = strtolower(trim($gateway));

        return array_key_exists($key, self::SUPPORTED) ? $key : null;
    }

    public function mercadoPago(): MercadoPagoService
    {
        return $this->mercadoPagoService;
    }
}
