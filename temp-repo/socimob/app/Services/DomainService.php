<?php

namespace App\Services;

use App\Models\Tenant;
use Illuminate\Support\Str;

class DomainService
{
    /**
     * Validar domínio
     */
    public function validateDomain(string $domain): bool
    {
        // Remover protocolo se existir
        $domain = str_replace(['http://', 'https://', 'www.'], '', $domain);

        // Validar formato de domínio
        $pattern = '/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?$/i';

        return preg_match($pattern, $domain) === 1;
    }

    /**
     * Normalizar domínio
     */
    public function normalizeDomain(string $domain): string
    {
        // Remover protocolo
        $domain = str_replace(['http://', 'https://'], '', $domain);

        // Remover www
        $domain = str_replace('www.', '', $domain);

        // Converter para minúsculas
        $domain = strtolower($domain);

        // Remover barra final
        $domain = rtrim($domain, '/');

        return $domain;
    }

    /**
     * Buscar tenant por domínio
     */
    public function findByDomain(string $domain): ?Tenant
    {
        $normalizedDomain = $this->normalizeDomain($domain);

        return Tenant::where('domain', $normalizedDomain)
            ->orWhere('domain', 'www.' . $normalizedDomain)
            ->first();
    }

    /**
     * Atualizar domínio do tenant
     */
    public function updateDomain(Tenant $tenant, string $newDomain): Tenant
    {
        if (!$this->validateDomain($newDomain)) {
            throw new \InvalidArgumentException("Domínio inválido: {$newDomain}");
        }

        $normalizedDomain = $this->normalizeDomain($newDomain);

        // Verificar se domínio já existe
        $existing = Tenant::where('domain', $normalizedDomain)
            ->where('id', '!=', $tenant->id)
            ->first();

        if ($existing) {
            throw new \InvalidArgumentException("Domínio '{$normalizedDomain}' já está em uso");
        }

        $tenant->update(['domain' => $normalizedDomain]);

        return $tenant;
    }

    /**
     * Gerar domínio sugerido a partir do nome
     */
    public function generateSuggestedDomain(string $tenantName): string
    {
        // Converter para slug
        $slug = Str::slug($tenantName);

        // Adicionar sufixo padrão
        $domain = $slug . '.exclusivallar.com.br';

        // Verificar se já existe
        $counter = 1;
        $originalDomain = $domain;

        while ($this->findByDomain($domain)) {
            $domain = str_replace('.exclusivallar.com.br', '-' . $counter . '.exclusivallar.com.br', $originalDomain);
            $counter++;
        }

        return $domain;
    }

    /**
     * Obter URL completa do tenant
     */
    public function getTenantUrl(Tenant $tenant, string $path = ''): string
    {
        $protocol = env('APP_ENV') === 'production' ? 'https' : 'http';
        $domain = $tenant->domain;

        $url = "{$protocol}://{$domain}";

        if ($path) {
            $url .= '/' . ltrim($path, '/');
        }

        return $url;
    }

    /**
     * Obter URL da API do tenant
     */
    public function getTenantApiUrl(Tenant $tenant, string $endpoint = ''): string
    {
        $protocol = env('APP_ENV') === 'production' ? 'https' : 'http';
        $domain = $tenant->domain;

        $url = "{$protocol}://{$domain}/api";

        if ($endpoint) {
            $url .= '/' . ltrim($endpoint, '/');
        }

        return $url;
    }

    /**
     * Validar DNS do domínio (verificar se aponta para servidor)
     */
    public function validateDNS(string $domain): bool
    {
        $normalizedDomain = $this->normalizeDomain($domain);
        $serverIp = gethostbyname($normalizedDomain);

        // Se retornar o mesmo domínio, DNS não foi resolvido
        if ($serverIp === $normalizedDomain) {
            return false;
        }

        // Comparar com IP do servidor
        $expectedIp = env('SERVER_IP');

        if (!$expectedIp) {
            // Se não houver IP esperado, apenas verificar se DNS foi resolvido
            return true;
        }

        return $serverIp === $expectedIp;
    }

    /**
     * Obter informações de DNS
     */
    public function getDNSInfo(string $domain): array
    {
        $normalizedDomain = $this->normalizeDomain($domain);

        $aRecord = gethostbyname($normalizedDomain);
        $mxRecords = dns_get_mx($normalizedDomain);
        $txtRecords = dns_get_record($normalizedDomain, DNS_TXT);

        return [
            'domain' => $normalizedDomain,
            'a_record' => $aRecord !== $normalizedDomain ? $aRecord : null,
            'mx_records' => $mxRecords ?: [],
            'txt_records' => $txtRecords ?: [],
            'is_valid' => $aRecord !== $normalizedDomain,
        ];
    }

    /**
     * Gerar instruções de configuração de DNS
     */
    public function generateDNSInstructions(Tenant $tenant): array
    {
        $serverIp = env('SERVER_IP', '1.2.3.4');
        $domain = $tenant->domain;

        return [
            'domain' => $domain,
            'records' => [
                [
                    'type' => 'A',
                    'name' => '@',
                    'value' => $serverIp,
                    'ttl' => 3600,
                    'description' => 'Aponta o domínio para o servidor',
                ],
                [
                    'type' => 'CNAME',
                    'name' => 'www',
                    'value' => $domain,
                    'ttl' => 3600,
                    'description' => 'Redireciona www para o domínio principal',
                ],
                [
                    'type' => 'MX',
                    'name' => '@',
                    'value' => 'mail.' . $domain,
                    'priority' => 10,
                    'ttl' => 3600,
                    'description' => 'Servidor de email',
                ],
            ],
            'instructions' => [
                'Acesse o painel de controle do seu registrador de domínio',
                'Procure pela seção "Gerenciar DNS" ou "Zone File"',
                'Adicione os registros listados acima',
                'Aguarde até 24 horas para propagação do DNS',
                'Verifique a configuração usando ferramentas online',
            ],
        ];
    }

    /**
     * Listar domínios alternativos do tenant
     */
    public function getAlternativeDomains(Tenant $tenant): array
    {
        // Retornar domínios alternativos (se houver suporte)
        // Por enquanto, retornar apenas o domínio principal
        return [
            [
                'domain' => $tenant->domain,
                'is_primary' => true,
                'is_verified' => true,
            ],
        ];
    }
}
