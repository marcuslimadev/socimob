<?php

namespace App\Services\WhatsApp;

use App\Models\Tenant;
use App\Models\TenantIntegration;
use App\Models\WhatsApp\WhatsAppAccount;
use App\Models\WhatsApp\WhatsAppPhoneNumber;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class WhatsAppConnectionService
{
    public function __construct(
        protected MetaCloudApiService $metaCloudApiService,
    ) {
    }

    public function connect(int $tenantId, array $payload, string $correlationId): array
    {
        return DB::transaction(function () use ($tenantId, $payload, $correlationId) {
            $tenant = Tenant::query()->findOrFail($tenantId);

            $integration = TenantIntegration::query()->updateOrCreate(
                [
                    'tenant_id' => $tenant->id,
                    'provider' => 'meta',
                    'channel' => 'whatsapp_cloud_api',
                ],
                [
                    'integration_version' => config('whatsapp.integration_version'),
                    'status' => 'connected',
                    'is_active' => true,
                    'credentials' => [
                        'app_id' => $payload['app_id'],
                        'app_secret' => $payload['app_secret'],
                        'access_token' => $payload['access_token'],
                    ],
                    'settings' => [
                        'graph_version' => $payload['graph_version'] ?? config('whatsapp.graph.version'),
                    ],
                    'webhook_url' => $payload['webhook_url'] ?? null,
                    'webhook_verify_token_hash' => !empty($payload['webhook_verify_token']) ? Hash::make($payload['webhook_verify_token']) : null,
                    'connected_at' => now(),
                    'last_validated_at' => now(),
                    'last_error_code' => null,
                    'last_error_message' => null,
                ]
            );

            $account = WhatsAppAccount::query()->updateOrCreate(
                [
                    'tenant_id' => $tenant->id,
                    'waba_id' => $payload['waba_id'],
                ],
                [
                    'tenant_integration_id' => $integration->id,
                    'meta_business_account_id' => $payload['meta_business_account_id'] ?? null,
                    'app_id' => $payload['app_id'],
                    'app_secret' => $payload['app_secret'],
                    'access_token' => $payload['access_token'],
                    'system_user_id' => $payload['system_user_id'] ?? null,
                    'status' => 'connected',
                    'display_name_status' => $payload['display_name_status'] ?? null,
                    'quality_rating' => $payload['quality_rating'] ?? null,
                    'metadata' => [
                        'migration_source' => $payload['migration_source'] ?? 'twilio',
                    ],
                    'connected_at' => now(),
                    'last_token_validated_at' => now(),
                ]
            );

            WhatsAppPhoneNumber::query()
                ->where('tenant_id', $tenant->id)
                ->update(['is_default' => false]);

            $phoneNumber = WhatsAppPhoneNumber::query()->updateOrCreate(
                [
                    'tenant_id' => $tenant->id,
                    'phone_number_id' => $payload['phone_number_id'],
                ],
                [
                    'whatsapp_account_id' => $account->id,
                    'display_phone_number' => $payload['display_phone_number'] ?? null,
                    'e164_phone_number' => $payload['e164_phone_number'] ?? null,
                    'verified_name' => $payload['verified_name'] ?? null,
                    'status' => 'connected',
                    'code_verification_status' => $payload['code_verification_status'] ?? null,
                    'quality_rating' => $payload['quality_rating'] ?? null,
                    'messaging_limit_tier' => $payload['messaging_limit_tier'] ?? null,
                    'current_provider' => 'meta_cloud',
                    'migrated_from_provider' => $payload['migration_source'] ?? 'twilio',
                    'is_default' => true,
                    'is_active' => true,
                ]
            );

            $health = [];

            try {
                $health = $this->metaCloudApiService->readPhoneNumberStatus($phoneNumber, $correlationId);
            } catch (\Throwable $exception) {
                $health = [
                    'health_check_error' => $exception->getMessage(),
                ];
            }

            $phoneNumber->forceFill([
                'metadata' => $health,
                'last_health_check_at' => now(),
            ])->save();

            return [
                'integration' => $integration->fresh(),
                'account' => $account->fresh(),
                'phone_number' => $phoneNumber->fresh(),
                'health' => $health,
            ];
        });
    }
}
