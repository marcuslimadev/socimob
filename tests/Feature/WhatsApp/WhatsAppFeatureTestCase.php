<?php

namespace Tests\Feature\WhatsApp;

use App\Models\Tenant;
use App\Models\TenantIntegration;
use App\Models\User;
use App\Models\WhatsApp\WhatsAppAccount;
use App\Models\WhatsApp\WhatsAppPhoneNumber;
use App\Support\SimpleAuthToken;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

abstract class WhatsAppFeatureTestCase extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('database.default', 'sqlite');
        config()->set('queue.default', 'sync');
        app()->forgetInstance('tenant');

        $this->rebuildSchema();
    }

    protected function createTenant(array $overrides = []): Tenant
    {
        return Tenant::query()->create(array_merge([
            'name' => 'Tenant WhatsApp',
            'slug' => 'tenant-whatsapp',
            'domain' => 'tenant-whatsapp.local',
            'is_active' => true,
        ], $overrides));
    }

    protected function createUser(Tenant $tenant, array $overrides = []): User
    {
        return User::query()->create(array_merge([
            'tenant_id' => $tenant->id,
            'name' => 'Admin WhatsApp',
            'email' => 'admin+' . $tenant->id . '@teste.local',
            'password' => bcrypt('secret'),
            'role' => 'admin',
            'is_active' => true,
        ], $overrides));
    }

    protected function createConnection(Tenant $tenant, array $overrides = []): array
    {
        $integration = TenantIntegration::query()->create([
            'tenant_id' => $tenant->id,
            'provider' => 'meta',
            'channel' => 'whatsapp_cloud_api',
            'integration_version' => 'test',
            'status' => 'connected',
            'is_active' => true,
            'credentials' => [
                'app_id' => '123456',
                'app_secret' => 'app-secret-test',
                'access_token' => 'access-token-test',
            ],
            'settings' => [],
            'connected_at' => now(),
        ]);

        $account = WhatsAppAccount::query()->create(array_merge([
            'tenant_id' => $tenant->id,
            'tenant_integration_id' => $integration->id,
            'meta_business_account_id' => 'business-account-1',
            'waba_id' => 'waba-1',
            'app_id' => '123456',
            'app_secret' => 'app-secret-test',
            'access_token' => 'access-token-test',
            'status' => 'connected',
            'connected_at' => now(),
        ], $overrides['account'] ?? []));

        $phoneNumber = WhatsAppPhoneNumber::query()->create(array_merge([
            'tenant_id' => $tenant->id,
            'whatsapp_account_id' => $account->id,
            'phone_number_id' => 'phone-number-id-1',
            'display_phone_number' => '+55 11 99999-9999',
            'e164_phone_number' => '5511999999999',
            'verified_name' => 'Tenant WhatsApp',
            'status' => 'connected',
            'current_provider' => 'meta_cloud',
            'is_default' => true,
            'is_active' => true,
        ], $overrides['phone_number'] ?? []));

        return compact('integration', 'account', 'phoneNumber');
    }

    protected function tenantHeaders(Tenant $tenant): array
    {
        return [
            'Accept' => 'application/json',
            'X-Tenant-Domain' => $tenant->domain,
        ];
    }

    protected function adminHeaders(User $user, ?Tenant $tenant = null): array
    {
        $tenant ??= $user->tenant;

        return array_merge($this->tenantHeaders($tenant), [
            'Authorization' => 'Bearer ' . SimpleAuthToken::issue($user->id),
        ]);
    }

    protected function rebuildSchema(): void
    {
        Schema::disableForeignKeyConstraints();

        foreach ([
            'integration_logs',
            'whatsapp_media',
            'whatsapp_webhook_events',
            'whatsapp_message_status_logs',
            'whatsapp_messages',
            'whatsapp_conversations',
            'whatsapp_contacts',
            'whatsapp_templates',
            'whatsapp_phone_numbers',
            'whatsapp_accounts',
            'tenant_integrations',
            'users',
            'tenants',
        ] as $table) {
            Schema::dropIfExists($table);
        }

        Schema::create('tenants', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('domain')->nullable();
            $table->string('slug')->nullable();
            $table->boolean('is_active')->default(true);
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('password')->nullable();
            $table->string('role')->default('admin');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('tenant_integrations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->string('provider', 50);
            $table->string('channel', 50);
            $table->string('integration_version', 30)->nullable();
            $table->string('status', 30)->default('inactive');
            $table->boolean('is_active')->default(false);
            $table->text('credentials')->nullable();
            $table->json('settings')->nullable();
            $table->string('webhook_url')->nullable();
            $table->string('webhook_verify_token_hash')->nullable();
            $table->timestamp('connected_at')->nullable();
            $table->timestamp('disconnected_at')->nullable();
            $table->timestamp('last_validated_at')->nullable();
            $table->string('last_error_code')->nullable();
            $table->text('last_error_message')->nullable();
            $table->timestamps();
        });

        Schema::create('whatsapp_accounts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('tenant_integration_id');
            $table->string('meta_business_account_id')->nullable();
            $table->string('waba_id');
            $table->string('app_id')->nullable();
            $table->text('app_secret')->nullable();
            $table->text('access_token')->nullable();
            $table->string('system_user_id')->nullable();
            $table->string('status')->default('inactive');
            $table->string('display_name_status')->nullable();
            $table->string('quality_rating')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('connected_at')->nullable();
            $table->timestamp('last_token_validated_at')->nullable();
            $table->timestamps();
        });

        Schema::create('whatsapp_phone_numbers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('whatsapp_account_id');
            $table->string('phone_number_id');
            $table->string('display_phone_number')->nullable();
            $table->string('e164_phone_number')->nullable();
            $table->string('verified_name')->nullable();
            $table->string('status')->default('inactive');
            $table->string('code_verification_status')->nullable();
            $table->string('quality_rating')->nullable();
            $table->string('messaging_limit_tier')->nullable();
            $table->string('current_provider')->default('meta_cloud');
            $table->string('migrated_from_provider')->nullable();
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_health_check_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('whatsapp_templates', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('whatsapp_account_id');
            $table->string('external_template_id')->nullable();
            $table->string('name');
            $table->string('language');
            $table->string('category')->nullable();
            $table->string('status')->nullable();
            $table->string('quality_score')->nullable();
            $table->string('parameter_format')->nullable();
            $table->json('components')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->text('rejected_reason')->nullable();
            $table->timestamps();
        });

        Schema::create('whatsapp_contacts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('whatsapp_phone_number_id')->nullable();
            $table->string('wa_id')->nullable();
            $table->string('phone_e164');
            $table->string('profile_name')->nullable();
            $table->string('contact_name')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('opted_in_at')->nullable();
            $table->timestamp('opted_out_at')->nullable();
            $table->timestamp('first_seen_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();
        });

        Schema::create('whatsapp_conversations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('whatsapp_account_id');
            $table->unsignedBigInteger('whatsapp_phone_number_id');
            $table->unsignedBigInteger('whatsapp_contact_id');
            $table->string('external_conversation_id')->nullable();
            $table->string('status')->default('open');
            $table->string('category')->nullable();
            $table->string('conversation_type')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('last_message_at')->nullable();
            $table->timestamp('last_inbound_at')->nullable();
            $table->timestamp('last_outbound_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('whatsapp_messages', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id');
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('whatsapp_account_id');
            $table->unsignedBigInteger('whatsapp_phone_number_id');
            $table->unsignedBigInteger('whatsapp_conversation_id')->nullable();
            $table->unsignedBigInteger('whatsapp_contact_id')->nullable();
            $table->string('wamid')->nullable();
            $table->string('direction');
            $table->string('message_type');
            $table->string('internal_status')->default('queued');
            $table->string('meta_message_status')->nullable();
            $table->string('idempotency_key')->nullable();
            $table->string('correlation_id')->nullable();
            $table->string('sender_phone')->nullable();
            $table->string('recipient_phone')->nullable();
            $table->text('body')->nullable();
            $table->string('template_name')->nullable();
            $table->string('template_language')->nullable();
            $table->json('template_payload')->nullable();
            $table->json('payload')->nullable();
            $table->string('context_message_wamid')->nullable();
            $table->string('reply_to_wamid')->nullable();
            $table->string('error_code')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('queued_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->timestamps();
        });

        Schema::create('whatsapp_message_status_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->unsignedBigInteger('whatsapp_message_id')->nullable();
            $table->string('wamid')->nullable();
            $table->string('status');
            $table->timestamp('status_at')->nullable();
            $table->string('recipient_id')->nullable();
            $table->boolean('billable')->nullable();
            $table->string('pricing_category')->nullable();
            $table->json('errors')->nullable();
            $table->json('raw_payload')->nullable();
            $table->timestamps();
        });

        Schema::create('whatsapp_webhook_events', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->unsignedBigInteger('whatsapp_account_id')->nullable();
            $table->unsignedBigInteger('whatsapp_phone_number_id')->nullable();
            $table->string('object_type')->nullable();
            $table->string('entry_id')->nullable();
            $table->string('change_field')->nullable();
            $table->string('event_type')->default('unknown');
            $table->string('delivery_key')->unique();
            $table->string('payload_hash');
            $table->boolean('signature_valid')->default(false);
            $table->unsignedInteger('attempt_count')->default(0);
            $table->string('correlation_id')->nullable();
            $table->json('headers')->nullable();
            $table->json('event_payload')->nullable();
            $table->longText('raw_payload')->nullable();
            $table->timestamp('queued_at')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();
        });

        Schema::create('whatsapp_media', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('whatsapp_account_id');
            $table->unsignedBigInteger('whatsapp_phone_number_id');
            $table->unsignedBigInteger('whatsapp_message_id')->nullable();
            $table->string('meta_media_id')->nullable();
            $table->string('direction')->nullable();
            $table->string('media_type')->nullable();
            $table->string('mime_type')->nullable();
            $table->string('sha256')->nullable();
            $table->string('filename')->nullable();
            $table->string('caption')->nullable();
            $table->string('storage_disk')->nullable();
            $table->string('storage_path')->nullable();
            $table->string('download_url')->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('uploaded_at')->nullable();
            $table->timestamp('downloaded_at')->nullable();
            $table->timestamps();
        });

        Schema::create('integration_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->string('integration_type');
            $table->string('integration_name');
            $table->string('channel')->nullable();
            $table->string('direction')->nullable();
            $table->string('operation')->nullable();
            $table->string('endpoint')->nullable();
            $table->string('correlation_id')->nullable();
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->boolean('success')->default(false);
            $table->unsignedInteger('latency_ms')->nullable();
            $table->string('error_code')->nullable();
            $table->text('error_message')->nullable();
            $table->json('request_payload')->nullable();
            $table->json('response_payload')->nullable();
            $table->timestamps();
        });

        Schema::enableForeignKeyConstraints();
    }
}
