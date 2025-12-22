<?php

namespace App\Http\Controllers;

use App\Models\Subscription;
use App\Models\Tenant;
use App\Services\MercadoPagoService;
use App\Services\PaymentGatewayResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class SubscriptionController extends Controller
{
    public function __construct(
        private MercadoPagoService $mercadoPagoService,
        private PaymentGatewayResolver $gatewayResolver
    ) {
    }

    /**
     * Obter assinatura atual do tenant
     * GET /api/subscriptions/current
     */
    public function current(Request $request)
    {
        $tenantId = $request->attributes->get('tenant_id');

        if (!$tenantId) {
            return response()->json(['error' => 'No tenant context'], 400);
        }

        $subscription = Subscription::where('tenant_id', $tenantId)->first();
        $gateway = $this->gatewayResolver->getActiveGateway();
        $plan = $this->buildPlanForTenant($tenantId, $gateway);

        if (!$subscription) {
            return response()->json([
                'subscription' => null,
                'plan' => $plan,
                'requires_subscription' => true,
                'contract_terms' => $this->contractTerms($plan['amount'], $gateway),
                'gateway' => $gateway,
                'gateway_label' => $this->gatewayResolver->getGatewayLabel($gateway),
            ]);
        }

        return response()->json([
            'subscription' => $subscription,
            'plan' => $plan,
            'requires_subscription' => !$subscription->isActive(),
            'contract_terms' => $this->contractTerms($plan['amount'], $gateway),
            'gateway' => $gateway,
            'gateway_label' => $this->gatewayResolver->getGatewayLabel($gateway),
        ]);
    }

    /**
     * Criar nova assinatura
     * POST /api/subscriptions
     */
    public function store(Request $request)
    {
        $tenantId = $request->attributes->get('tenant_id');

        if (!$tenantId) {
            return response()->json(['error' => 'No tenant context'], 400);
        }

        // Verificar se é admin do tenant
        if (!$request->user()->isAdmin() || $request->user()->tenant_id !== $tenantId) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $tenant = Tenant::find($tenantId);

        if (!$tenant) {
            return response()->json(['error' => 'Tenant not found'], 404);
        }

        $validated = $request->validate([
            'card_number' => 'required|string|regex:/^\d{13,19}$/',
            'card_holder_name' => 'required|string|max:255',
            'card_exp_month' => 'required|integer|min:1|max:12',
            'card_exp_year' => 'required|integer|min:' . date('Y'),
            'card_cvv' => 'required|string|regex:/^\d{3,4}$/',
            'card_document' => 'nullable|string|max:20',
            'accept_contract' => 'required|boolean|in:1,true,yes,on',
        ]);

        $gateway = $this->gatewayResolver->getActiveGateway();
        $plan = $this->buildPlanForTenant($tenantId, $gateway);

        if (!$this->gatewayResolver->isImplemented($gateway)) {
            return response()->json([
                'error' => 'Gateway de pagamento não implementado',
                'message' => 'Configure um gateway compatível (Mercado Pago) para ativar a assinatura.',
                'gateway' => $gateway,
            ], 422);
        }

        try {
            $customer = $this->mercadoPagoService->createOrUpdateCustomer([
                'email' => $tenant->contact_email ?? $request->user()->email,
                'first_name' => $tenant->name,
                'phone' => $tenant->contact_phone,
                'identification' => [
                    'type' => 'CPF',
                    'number' => $validated['card_document'] ?? '00000000000',
                ],
            ]);

            $tenant->update(['mercado_pago_customer_id' => $customer['id'] ?? null]);

            $cardToken = $this->mercadoPagoService->createCardToken([
                'card_number' => $validated['card_number'],
                'security_code' => $validated['card_cvv'],
                'expiration_month' => $validated['card_exp_month'],
                'expiration_year' => $validated['card_exp_year'],
                'holder_name' => $validated['card_holder_name'],
                'document' => $validated['card_document'] ?? '00000000000',
            ]);

            $preapproval = $this->mercadoPagoService->createPreapproval([
                'reason' => $plan['name'],
                'payer_email' => $customer['email'] ?? $tenant->contact_email ?? $request->user()->email,
                'card_token_id' => $cardToken['id'],
                'amount' => $plan['amount'],
                'back_url' => url('/app/dashboard.html'),
                'external_reference' => 'tenant_' . $tenantId,
            ]);

            $subscription = Subscription::create([
                'tenant_id' => $tenantId,
                'plan_id' => $plan['id'],
                'plan_name' => $plan['name'],
                'plan_amount' => $plan['amount'],
                'plan_interval' => $plan['interval'],
                'status' => 'active',
                'gateway' => $gateway,
                'mercado_pago_preapproval_id' => $preapproval['id'] ?? null,
                'mercado_pago_customer_id' => $customer['id'] ?? null,
                'mercado_pago_card_token' => $cardToken['id'] ?? null,
                'payment_method' => 'credit_card',
                'card_last_four' => substr($validated['card_number'], -4),
                'card_brand' => $this->detectCardBrand($validated['card_number']),
                'current_period_start' => now(),
                'current_period_end' => $this->calculatePeriodEnd($plan['interval']),
                'metadata' => [
                    'contract_terms' => $this->contractTerms($plan['amount'], $gateway),
                ],
            ]);

            $tenant->update([
                'subscription_status' => 'active',
                'subscription_plan' => $plan['id'],
                'subscription_expires_at' => $subscription->current_period_end,
                'subscription_started_at' => now(),
                'mercado_pago_customer_id' => $customer['id'] ?? null,
                'mercado_pago_preapproval_id' => $preapproval['id'] ?? null,
            ]);

            Log::info('Subscription created successfully', [
                'tenant_id' => $tenantId,
                'subscription_id' => $subscription->id,
                'mercado_pago_preapproval_id' => $preapproval['id'] ?? null,
            ]);

            return response()->json([
                'message' => 'Assinatura criada com sucesso via ' . $this->gatewayResolver->getGatewayLabel($gateway),
                'subscription' => $subscription,
                'contract_terms' => $this->contractTerms($plan['amount'], $gateway),
            ], 201);
        } catch (\Exception $e) {
            Log::error('Failed to create subscription', [
                'tenant_id' => $tenantId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Failed to create subscription',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Cancelar assinatura
     * POST /api/subscriptions/cancel
     */
    public function cancel(Request $request)
    {
        $tenantId = $request->attributes->get('tenant_id');

        if (!$tenantId) {
            return response()->json(['error' => 'No tenant context'], 400);
        }

        // Verificar se é admin do tenant
        if (!$request->user()->isAdmin() || $request->user()->tenant_id !== $tenantId) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $subscription = Subscription::where('tenant_id', $tenantId)->first();

        if (!$subscription) {
            return response()->json(['error' => 'No subscription found'], 404);
        }

        try {
            if ($subscription->gateway === 'mercado_pago' && $subscription->mercado_pago_preapproval_id) {
                $this->mercadoPagoService->cancelPreapproval($subscription->mercado_pago_preapproval_id);
            }

            // Atualizar localmente
            $subscription->cancel('Cancelada pelo usuário');

            $tenant = Tenant::find($tenantId);
            $tenant->update([
                'subscription_status' => 'canceled',
            ]);

            Log::info('Subscription canceled', [
                'tenant_id' => $tenantId,
                'subscription_id' => $subscription->id,
            ]);

            return response()->json([
                'message' => 'Subscription canceled successfully',
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to cancel subscription', [
                'tenant_id' => $tenantId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Failed to cancel subscription',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Atualizar cartão de crédito
     * PUT /api/subscriptions/card
     */
    public function updateCard(Request $request)
    {
        $tenantId = $request->attributes->get('tenant_id');

        if (!$tenantId) {
            return response()->json(['error' => 'No tenant context'], 400);
        }

        // Verificar se é admin do tenant
        if (!$request->user()->isAdmin() || $request->user()->tenant_id !== $tenantId) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $subscription = Subscription::where('tenant_id', $tenantId)->first();

        if (!$subscription) {
            return response()->json(['error' => 'No subscription found'], 404);
        }

        if ($subscription->gateway !== 'mercado_pago') {
            return response()->json([
                'error' => 'Gateway não suporta atualização de cartão aqui',
                'message' => 'Atualize o cartão diretamente no gateway configurado.',
            ], 422);
        }

        $validated = $request->validate([
            'card_number' => 'required|string|regex:/^\d{13,19}$/',
            'card_holder_name' => 'required|string|max:255',
            'card_exp_month' => 'required|integer|min:1|max:12',
            'card_exp_year' => 'required|integer|min:' . date('Y'),
            'card_cvv' => 'required|string|regex:/^\d{3,4}$/',
            'card_document' => 'nullable|string|max:20',
        ]);

        try {
            $cardToken = $this->mercadoPagoService->createCardToken([
                'card_number' => $validated['card_number'],
                'security_code' => $validated['card_cvv'],
                'expiration_month' => $validated['card_exp_month'],
                'expiration_year' => $validated['card_exp_year'],
                'holder_name' => $validated['card_holder_name'],
                'document' => $validated['card_document'] ?? '00000000000',
            ]);

            if ($subscription->mercado_pago_preapproval_id) {
                $this->mercadoPagoService->updatePreapprovalCard($subscription->mercado_pago_preapproval_id, $cardToken['id']);
            }

            $subscription->update([
                'mercado_pago_card_token' => $cardToken['id'],
                'card_last_four' => substr($validated['card_number'], -4),
                'card_brand' => $this->detectCardBrand($validated['card_number']),
            ]);

            Log::info('Subscription card updated', [
                'tenant_id' => $tenantId,
                'subscription_id' => $subscription->id,
            ]);

            return response()->json([
                'message' => 'Card updated successfully',
                'subscription' => $subscription,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to update card', [
                'tenant_id' => $tenantId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Failed to update card',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Webhook do Mercado Pago
     * POST /api/subscriptions/webhook
     */
    public function webhook(Request $request)
    {
        $payload = $request->all();

        Log::info('Mercado Pago webhook recebido', ['payload' => $payload]);

        $preapprovalId = $payload['data']['id'] ?? $payload['id'] ?? null;

        if (!$preapprovalId) {
            return response()->json(['error' => 'preapproval id ausente'], 400);
        }

        try {
            $preapproval = $this->mercadoPagoService->getPreapproval($preapprovalId);
            $subscription = Subscription::where('mercado_pago_preapproval_id', $preapprovalId)->first();

            if (!$subscription) {
                Log::warning('Assinatura não encontrada para preapproval', ['preapproval_id' => $preapprovalId]);
                return response()->json(['ignored' => true]);
            }

            if ($subscription->gateway !== 'mercado_pago') {
                Log::warning('Webhook ignorado para gateway divergente', [
                    'gateway' => $subscription->gateway,
                    'preapproval_id' => $preapprovalId,
                ]);

                return response()->json(['ignored' => true]);
            }

            $status = $preapproval['status'] ?? null;

            if ($status === 'authorized') {
                $subscription->markAsActive();
            } elseif (in_array($status, ['paused', 'pending'])) {
                $subscription->markAsPastDue('Assinatura pausada ou pendente no Mercado Pago');
            } elseif (in_array($status, ['cancelled', 'expired'])) {
                $subscription->cancel('Cancelada no Mercado Pago');
            }

            return response()->json(['processed' => true, 'status' => $status]);
        } catch (\Exception $e) {
            Log::error('Erro ao processar webhook do Mercado Pago', [
                'preapproval_id' => $preapprovalId,
                'error' => $e->getMessage(),
            ]);

            return response()->json(['error' => 'failed'], 500);
        }
    }

    /**
     * Detectar marca do cartão
     */
    private function detectCardBrand(string $cardNumber): string
    {
        $cardNumber = preg_replace('/\D/', '', $cardNumber);
        $firstDigit = $cardNumber[0];

        if (in_array(substr($cardNumber, 0, 2), ['51', '52', '53', '54', '55'])) {
            return 'mastercard';
        } elseif (in_array(substr($cardNumber, 0, 4), ['4011', '4012', '4013', '4014', '4015', '4016', '4017', '4018', '4019']) || $firstDigit === '4') {
            return 'visa';
        } elseif (in_array(substr($cardNumber, 0, 4), ['3782', '3783', '3784', '3785', '3786', '3787', '3788'])) {
            return 'amex';
        } elseif (in_array(substr($cardNumber, 0, 4), ['6011', '622126', '622127', '622128', '622129', '622130', '622131', '622132', '622133', '622134', '622135', '622136', '622137', '622138', '622139', '622140', '622141', '622142', '622143', '622144', '622145', '622146', '622147', '622148', '622149', '622150', '622151', '622152', '622153', '622154', '622155', '622156', '622157', '622158', '622159', '622160', '622161', '622162', '622163', '622164', '622165'])) {
            return 'discover';
        }

        return 'unknown';
    }

    /**
     * Calcular data de término do período
     */
    private function calculatePeriodEnd(string $interval): \DateTime
    {
        if ($interval === 'month') {
            return now()->addMonth();
        }

        return now()->addYear();
    }

    private function buildPlanForTenant(int $tenantId, string $gateway): array
    {
        $amount = $this->getHalfMinimumWage();
        $gatewayLabel = $this->gatewayResolver->getGatewayLabel($gateway);

        return [
            'id' => 'assinatura-meio-salario-minimo',
            'name' => 'Assinatura mensal indexada a 50% do salário mínimo',
            'amount' => $amount,
            'interval' => 'month',
            'description' => $this->contractTerms($amount, $gateway),
            'gateway' => $gateway,
            'gateway_label' => $gatewayLabel,
            'tenant_id' => $tenantId,
        ];
    }

    private function contractTerms(float $amount, string $gateway): string
    {
        $gatewayLabel = $this->gatewayResolver->getGatewayLabel($gateway);

        return sprintf(
            'Assinatura recorrente mensal no valor de 50%% do salário mínimo vigente (atualmente R$ %.2f), cobrada automaticamente via %s diretamente na conta do titular. Ao prosseguir, o contratante autoriza cobranças recorrentes até cancelamento.',
            $amount,
            $gatewayLabel
        );
    }

    private function getHalfMinimumWage(): float
    {
        $minimum = (float) env('MINIMUM_WAGE_BRL', 1412.00);
        return round($minimum * 0.5, 2);
    }
}
