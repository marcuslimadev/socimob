<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Lead;
use App\Models\Property;
use App\Models\Subscription;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    /**
     * Obter dashboard global do Super Admin
     * GET /api/super-admin/dashboard
     */
    public function index(Request $request)
    {
        // Verificar se é super admin
        if (!$request->user() || !$request->user()->isSuperAdmin()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $stats = [
            'tenants' => $this->getTenantStats(),
            'users' => $this->getUserStats(),
            'properties' => $this->getPropertyStats(),
            'leads' => $this->getLeadStats(),
            'subscriptions' => $this->getSubscriptionStats(),
            'revenue' => $this->getRevenueStats(),
            'recent_tenants' => $this->getRecentTenants(),
            'recent_subscriptions' => $this->getRecentSubscriptions(),
        ];

        return response()->json($stats);
    }

    /**
     * Obter estatísticas de tenants
     */
    private function getTenantStats(): array
    {
        return [
            'total' => Tenant::count(),
            'active' => Tenant::where('is_active', true)->count(),
            'inactive' => Tenant::where('is_active', false)->count(),
            'subscribed' => Tenant::where('subscription_status', 'active')->count(),
            'suspended' => Tenant::where('subscription_status', 'suspended')->count(),
            'expired' => Tenant::where('subscription_status', 'expired')->count(),
        ];
    }

    /**
     * Obter estatísticas de usuários
     */
    private function getUserStats(): array
    {
        return [
            'total' => User::count(),
            'super_admins' => User::where('role', 'super_admin')->count(),
            'admins' => User::where('role', 'admin')->count(),
            'correctores' => User::where('role', 'corretor')->count(),
            'clientes' => User::where('role', 'cliente')->count(),
            'active' => User::where('ativo', true)->count(),
            'inactive' => User::where('ativo', false)->count(),
        ];
    }

    /**
     * Obter estatísticas de imóveis
     */
    private function getPropertyStats(): array
    {
        return [
            'total' => Property::count(),
            'active' => Property::where('active', true)->count(),
            'inactive' => Property::where('active', false)->count(),
            'for_sale' => Property::where('finalidade_imovel', 'venda')->count(),
            'for_rent' => Property::where('finalidade_imovel', 'aluguel')->count(),
        ];
    }

    /**
     * Obter estatísticas de leads
     */
    private function getLeadStats(): array
    {
        return [
            'total' => Lead::count(),
            'novo' => Lead::where('status', 'novo')->count(),
            'em_andamento' => Lead::where('status', 'em_andamento')->count(),
            'convertido' => Lead::where('status', 'convertido')->count(),
            'perdido' => Lead::where('status', 'perdido')->count(),
            'com_score_alto' => Lead::where('score', '>=', 80)->count(),
        ];
    }

    /**
     * Obter estatísticas de assinaturas
     */
    private function getSubscriptionStats(): array
    {
        return [
            'total' => Subscription::count(),
            'active' => Subscription::where('status', 'active')->count(),
            'past_due' => Subscription::where('status', 'past_due')->count(),
            'canceled' => Subscription::where('status', 'canceled')->count(),
            'paused' => Subscription::where('status', 'paused')->count(),
        ];
    }

    /**
     * Obter estatísticas de receita
     */
    private function getRevenueStats(): array
    {
        $activeSubscriptions = Subscription::where('status', 'active')->get();
        
        $monthlyRecurring = 0;
        $yearlyRecurring = 0;

        foreach ($activeSubscriptions as $subscription) {
            if ($subscription->plan_interval === 'month') {
                $monthlyRecurring += $subscription->plan_amount;
            } elseif ($subscription->plan_interval === 'year') {
                $yearlyRecurring += $subscription->plan_amount / 12;
            }
        }

        return [
            'monthly_recurring_revenue' => $monthlyRecurring,
            'annual_recurring_revenue' => $monthlyRecurring * 12,
            'total_active_subscriptions' => $activeSubscriptions->count(),
            'average_subscription_value' => $activeSubscriptions->count() > 0 
                ? $monthlyRecurring / $activeSubscriptions->count() 
                : 0,
        ];
    }

    /**
     * Obter tenants criados recentemente
     */
    private function getRecentTenants(int $limit = 5): array
    {
        return Tenant::latest('created_at')
            ->limit($limit)
            ->get()
            ->map(function ($tenant) {
                return [
                    'id' => $tenant->id,
                    'name' => $tenant->name,
                    'domain' => $tenant->domain,
                    'subscription_status' => $tenant->subscription_status,
                    'created_at' => $tenant->created_at,
                ];
            })
            ->toArray();
    }

    /**
     * Obter assinaturas recentes
     */
    private function getRecentSubscriptions(int $limit = 5): array
    {
        return Subscription::with('tenant')
            ->latest('created_at')
            ->limit($limit)
            ->get()
            ->map(function ($subscription) {
                return [
                    'id' => $subscription->id,
                    'tenant_name' => $subscription->tenant->name,
                    'plan_name' => $subscription->plan_name,
                    'plan_amount' => $subscription->plan_amount,
                    'status' => $subscription->status,
                    'created_at' => $subscription->created_at,
                ];
            })
            ->toArray();
    }

    /**
     * Obter gráfico de crescimento de tenants
     * GET /api/super-admin/dashboard/growth
     */
    public function growth(Request $request)
    {
        if (!$request->user() || !$request->user()->isSuperAdmin()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $months = $request->query('months', 12);
        
        $data = [];
        for ($i = $months - 1; $i >= 0; $i--) {
            $date = now()->subMonths($i);
            $count = Tenant::whereYear('created_at', $date->year)
                ->whereMonth('created_at', $date->month)
                ->count();
            
            $data[] = [
                'month' => $date->format('M/Y'),
                'tenants' => $count,
            ];
        }

        return response()->json($data);
    }

    /**
     * Obter gráfico de receita
     * GET /api/super-admin/dashboard/revenue
     */
    public function revenue(Request $request)
    {
        if (!$request->user() || !$request->user()->isSuperAdmin()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $months = $request->query('months', 12);
        
        $data = [];
        for ($i = $months - 1; $i >= 0; $i--) {
            $date = now()->subMonths($i);
            
            $subscriptions = Subscription::where('status', 'active')
                ->whereYear('created_at', '<=', $date->year)
                ->whereMonth('created_at', '<=', $date->month)
                ->get();
            
            $revenue = $subscriptions->sum(function ($sub) {
                return $sub->plan_interval === 'month' 
                    ? $sub->plan_amount 
                    : $sub->plan_amount / 12;
            });
            
            $data[] = [
                'month' => $date->format('M/Y'),
                'revenue' => $revenue,
            ];
        }

        return response()->json($data);
    }

    /**
     * Obter distribuição de planos
     * GET /api/super-admin/dashboard/plans
     */
    public function plans(Request $request)
    {
        if (!$request->user() || !$request->user()->isSuperAdmin()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $plans = Subscription::where('status', 'active')
            ->selectRaw('plan_name, COUNT(*) as count, SUM(plan_amount) as total_revenue')
            ->groupBy('plan_name')
            ->get();

        return response()->json($plans);
    }
}
