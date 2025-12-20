<?php

namespace App\Services;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class TenantService
{
    /**
     * Obter tenant atual
     *
     * @return Tenant|null
     */
    public function current(): ?Tenant
    {
        return app('tenant') ?? null;
    }

    /**
     * Obter ID do tenant atual
     *
     * @return int|null
     */
    public function currentId(): ?int
    {
        $tenant = $this->current();
        return $tenant?->id;
    }

    /**
     * Verificar se há um tenant no contexto
     *
     * @return bool
     */
    public function hasCurrent(): bool
    {
        return $this->current() !== null;
    }

    /**
     * Buscar tenant por domínio
     *
     * @param  string  $domain
     * @return Tenant|null
     */
    public function findByDomain(string $domain): ?Tenant
    {
        return Tenant::byDomain($domain)->first();
    }

    /**
     * Buscar tenant por ID
     *
     * @param  int  $id
     * @return Tenant|null
     */
    public function findById(int $id): ?Tenant
    {
        return Tenant::find($id);
    }

    /**
     * Buscar tenant por slug
     *
     * @param  string  $slug
     * @return Tenant|null
     */
    public function findBySlug(string $slug): ?Tenant
    {
        return Tenant::where('slug', $slug)->first();
    }

    /**
     * Criar novo tenant
     *
     * @param  array  $data
     * @return Tenant
     */
    public function create(array $data): Tenant
    {
        // Gerar slug a partir do nome
        $data['slug'] = Str::slug($data['name']);

        // Garantir que o slug é único
        $originalSlug = $data['slug'];
        $counter = 1;
        while (Tenant::where('slug', $data['slug'])->exists()) {
            $data['slug'] = $originalSlug . '-' . $counter;
            $counter++;
        }

        // Gerar token de API
        $data['api_token'] = 'tenant_' . bin2hex(random_bytes(32));

        $tenant = Tenant::create($data);

        // Criar usuário admin se senha fornecida
        if (!empty($data['admin_password'])) {
            User::create([
                'tenant_id' => $tenant->id,
                'name' => $data['name'],
                'email' => $data['contact_email'] ?? $data['email'] ?? 'admin@' . $data['domain'],
                'password' => Hash::make($data['admin_password']),
                'role' => $data['admin_role'] ?? 'admin',
                'is_active' => true,
            ]);
        }

        // Criar configurações padrão
        $tenant->config()->create([
            'primary_color' => '#000000',
            'secondary_color' => '#FFFFFF',
            'accent_color' => '#FF6B6B',
        ]);

        return $tenant;
    }

    /**
     * Atualizar tenant
     *
     * @param  Tenant  $tenant
     * @param  array  $data
     * @return Tenant
     */
    public function update(Tenant $tenant, array $data): Tenant
    {
        $tenant->update($data);

        // Atualizar ou criar usuário admin se senha fornecida
        if (!empty($data['admin_password'])) {
            $email = $data['contact_email'] ?? $tenant->contact_email;
            
            if ($email) {
                $user = User::where('tenant_id', $tenant->id)
                    ->where('email', $email)
                    ->first();

                if ($user) {
                    $user->update([
                        'password' => Hash::make($data['admin_password']),
                        'role' => $data['admin_role'] ?? $user->role,
                    ]);
                } else {
                    User::create([
                        'tenant_id' => $tenant->id,
                        'name' => $data['name'] ?? $tenant->name,
                        'email' => $email,
                        'password' => Hash::make($data['admin_password']),
                        'role' => $data['admin_role'] ?? 'admin',
                        'is_active' => true,
                    ]);
                }
            }
        }

        return $tenant;
    }

    /**
     * Deletar tenant
     *
     * @param  Tenant  $tenant
     * @return bool
     */
    public function delete(Tenant $tenant): bool
    {
        return $tenant->delete();
    }

    /**
     * Ativar tenant
     *
     * @param  Tenant  $tenant
     * @return Tenant
     */
    public function activate(Tenant $tenant): Tenant
    {
        $tenant->activate();
        return $tenant;
    }

    /**
     * Desativar tenant
     *
     * @param  Tenant  $tenant
     * @return Tenant
     */
    public function deactivate(Tenant $tenant): Tenant
    {
        $tenant->deactivate();
        return $tenant;
    }

    /**
     * Suspender assinatura
     *
     * @param  Tenant  $tenant
     * @param  string|null  $reason
     * @return Tenant
     */
    public function suspendSubscription(Tenant $tenant, string $reason = null): Tenant
    {
        $tenant->suspendSubscription($reason);
        return $tenant;
    }

    /**
     * Ativar assinatura
     *
     * @param  Tenant  $tenant
     * @return Tenant
     */
    public function activateSubscription(Tenant $tenant): Tenant
    {
        $tenant->activateSubscription();
        return $tenant;
    }

    /**
     * Listar todos os tenants
     *
     * @param  int  $perPage
     * @return \Illuminate\Pagination\LengthAwarePaginator
     */
    public function all(int $perPage = 15)
    {
        return Tenant::paginate($perPage);
    }

    /**
     * Listar tenants ativos
     *
     * @param  int  $perPage
     * @return \Illuminate\Pagination\LengthAwarePaginator
     */
    public function active(int $perPage = 15)
    {
        return Tenant::active()->paginate($perPage);
    }

    /**
     * Listar tenants com assinatura ativa
     *
     * @param  int  $perPage
     * @return \Illuminate\Pagination\LengthAwarePaginator
     */
    public function subscribed(int $perPage = 15)
    {
        return Tenant::subscribed()->paginate($perPage);
    }

    /**
     * Obter estatísticas de um tenant
     *
     * @param  Tenant  $tenant
     * @return array
     */
    public function getStats(Tenant $tenant): array
    {
        return [
            'users_count' => $tenant->users()->count(),
            'admins_count' => $tenant->users()->where('role', 'admin')->count(),
            'correctores_count' => $tenant->users()->where('role', 'corretor')->count(),
            'clientes_count' => $tenant->users()->where('role', 'cliente')->count(),
            'properties_count' => $tenant->properties()->count(),
            'leads_count' => $tenant->leads()->count(),
            'is_subscribed' => $tenant->isSubscribed(),
            'subscription_expires_at' => $tenant->subscription_expires_at,
        ];
    }
}
