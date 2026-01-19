<?php

namespace App\Http\Controllers;

use App\Models\Subscription;
use App\Models\Tenant;
use App\Services\PagarMeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class SubscriptionController extends Controller
{
    protected $pagarMeService;

    public function __construct(PagarMeService $pagarMeService)
    {
        $this->pagarMeService = $pagarMeService;
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

        if (!$subscription) {
            return response()->json(['error' => 'No subscription found'], 404);
        }

        return response()->json($subscription);
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
            'plan_id' => 'required|string|max:50',
            'plan_name' => 'required|string|max:255',
            'plan_amount' => 'required|numeric|min:0.01',
            'plan_interval' => 'required|in:month,year',
            'card_number' => 'required|string|regex:/^\d{13,19}$/',
            'card_holder_name' => 'required|string|max:255',
            'card_exp_month' => 'required|integer|min:1|max:12',
            'card_exp_year' => 'required|integer|min:' . date('Y'),
            'card_cvv' => 'required|string|regex:/^\d{3,4}$/',
            'billing_address_street' => 'required|string|max:255',
            'billing_address_number' => 'required|string|max:20',
            'billing_address_zip_code' => 'required|string|max:10',
            'billing_address_city' => 'required|string|max:100',
            'billing_address_state' => 'required|string|max:2',
        ]);

        try {
            // 1. Criar cliente no Pagar.me (se não existir)
            if (!$tenant->pagar_me_customer_id) {
                $customerData = [
                    'name' => $tenant->name,
                    'email' => $tenant->contact_email,
                    'phone' => $tenant->contact_phone,
                ];

                $customer = $this->pagarMeService->createCustomer($customerData);
                $tenant->update(['pagar_me_customer_id' => $customer['id']]);
            }

            // 2. Criar cartão
            $cardData = [
                'number' => $validated['card_number'],
                'holder_name' => $validated['card_holder_name'],
                'exp_month' => $validated['card_exp_month'],
                'exp_year' => $validated['card_exp_year'],
                'cvv' => $validated['card_cvv'],
                'street' => $validated['billing_address_street'],
                'number_address' => $validated['billing_address_number'],
                'zip_code' => $validated['billing_address_zip_code'],
                'city' => $validated['billing_address_city'],
                'state' => $validated['billing_address_state'],
            ];

            $card = $this->pagarMeService->createCard($tenant->pagar_me_customer_id, $cardData);

            // 3. Criar assinatura
            $planData = [
                'plan_id' => $validated['plan_id'],
                'description' => $validated['plan_name'],
                'amount' => $validated['plan_amount'],
                'interval' => $validated['plan_interval'],
                'interval_count' => 1,
                'metadata' => [
                    'tenant_id' => $tenantId,
                    'plan_name' => $validated['plan_name'],
                ],
            ];

            $pagarMeSubscription = $this->pagarMeService->createSubscription(
                $tenant->pagar_me_customer_id,
                $card['id'],
                $planData
            );

            // 4. Salvar assinatura localmente
            $subscription = Subscription::create([
                'tenant_id' => $tenantId,
                'plan_id' => $validated['plan_id'],
                'plan_name' => $validated['plan_name'],
                'plan_amount' => $validated['plan_amount'],
                'plan_interval' => $validated['plan_interval'],
                'status' => 'active',
                'pagar_me_subscription_id' => $pagarMeSubscription['id'],
                'pagar_me_customer_id' => $tenant->pagar_me_customer_id,
                'pagar_me_card_id' => $card['id'],
                'payment_method' => 'credit_card',
                'card_last_four' => substr($validated['card_number'], -4),
                'card_brand' => $this->detectCardBrand($validated['card_number']),
                'current_period_start' => now(),
                'current_period_end' => $this->calculatePeriodEnd($validated['plan_interval']),
            ]);

            // 5. Atualizar tenant
            $tenant->update([
                'subscription_status' => 'active',
                'subscription_plan' => $validated['plan_id'],
                'subscription_expires_at' => $subscription->current_period_end,
                'subscription_started_at' => now(),
                'pagar_me_subscription_id' => $pagarMeSubscription['id'],
            ]);

            Log::info('Subscription created successfully', [
                'tenant_id' => $tenantId,
                'subscription_id' => $subscription->id,
                'pagar_me_subscription_id' => $pagarMeSubscription['id'],
            ]);

            return response()->json([
                'message' => 'Subscription created successfully',
                'subscription' => $subscription,
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
            // Cancelar no Pagar.me
            $this->pagarMeService->cancelSubscription($subscription->pagar_me_subscription_id);

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

        $validated = $request->validate([
            'card_number' => 'required|string|regex:/^\d{13,19}$/',
            'card_holder_name' => 'required|string|max:255',
            'card_exp_month' => 'required|integer|min:1|max:12',
            'card_exp_year' => 'required|integer|min:' . date('Y'),
            'card_cvv' => 'required|string|regex:/^\d{3,4}$/',
            'billing_address_street' => 'required|string|max:255',
            'billing_address_number' => 'required|string|max:20',
            'billing_address_zip_code' => 'required|string|max:10',
            'billing_address_city' => 'required|string|max:100',
            'billing_address_state' => 'required|string|max:2',
        ]);

        try {
            $tenant = Tenant::find($tenantId);

            // Criar novo cartão
            $cardData = [
                'number' => $validated['card_number'],
                'holder_name' => $validated['card_holder_name'],
                'exp_month' => $validated['card_exp_month'],
                'exp_year' => $validated['card_exp_year'],
                'cvv' => $validated['card_cvv'],
                'street' => $validated['billing_address_street'],
                'number_address' => $validated['billing_address_number'],
                'zip_code' => $validated['billing_address_zip_code'],
                'city' => $validated['billing_address_city'],
                'state' => $validated['billing_address_state'],
            ];

            $card = $this->pagarMeService->createCard($tenant->pagar_me_customer_id, $cardData);

            // Atualizar assinatura com novo cartão
            $this->pagarMeService->updateSubscription($subscription->pagar_me_subscription_id, [
                'card_id' => $card['id'],
            ]);

            // Atualizar localmente
            $subscription->update([
                'pagar_me_card_id' => $card['id'],
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
     * Webhook do Pagar.me
     * POST /api/webhooks/pagar-me
     */
    public function webhook(Request $request)
    {
        $payload = $request->all();

        Log::info('Pagar.me webhook received', ['payload' => $payload]);

        try {
            // Verificar assinatura do webhook (opcional, mas recomendado)
            // $signature = $request->header('X-Hub-Signature');
            // if (!$this->pagarMeService->verifyWebhookSignature(json_encode($payload), $signature)) {
            //     return response()->json(['error' => 'Invalid signature'], 401);
            // }

            $this->pagarMeService->handleWebhook($payload);

            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            Log::error('Webhook processing error', [
                'error' => $e->getMessage(),
            ]);

            return response()->json(['error' => 'Webhook processing failed'], 500);
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
        } elseif ($interval === 'year') {
            return now()->addYear();
        }

        return now()->addMonth();
    }
}
