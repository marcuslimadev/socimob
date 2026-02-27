<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cria todas as tabelas do módulo Ads Automation.
     * Provider values: 'meta', 'google'
     * Status values: DRAFT, CONNECTED, READY, PUBLISHING, ACTIVE, PAUSED, ERROR
     */
    public function up(): void
    {
        // 1. Conexões OAuth por tenant/provider
        if (!Schema::hasTable('ads_connections')) {
            Schema::create('ads_connections', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('tenant_id')->index();
                $table->string('provider', 20); // meta, google
                $table->string('status', 20)->default('DRAFT'); // DRAFT|CONNECTED|READY|ERROR
                $table->text('token_enc')->nullable();           // access_token criptografado
                $table->text('refresh_token_enc')->nullable();   // refresh_token criptografado
                $table->text('scopes')->nullable();              // scopes concedidos (JSON array)
                $table->timestamp('expires_at')->nullable();
                $table->string('external_user_id', 100)->nullable(); // FB user_id ou Google sub
                $table->string('external_business_id', 100)->nullable(); // Meta: business/portfolio id
                $table->json('metadata_json')->nullable();       // dados extras do provider
                $table->timestamp('last_refresh_at')->nullable();
                $table->timestamp('disconnected_at')->nullable();
                $table->timestamps();

                $table->unique(['tenant_id', 'provider'], 'uq_ads_conn_tenant_provider');
                $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            });
        }

        // 2. Contas de anúncio (Ad Account) por tenant/provider
        if (!Schema::hasTable('ads_accounts')) {
            Schema::create('ads_accounts', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('tenant_id')->index();
                $table->string('provider', 20);
                $table->string('external_account_id', 100);  // act_XXXXX (Meta) ou ID numérico (Google)
                $table->string('name', 255)->nullable();
                $table->string('currency', 10)->default('BRL');
                $table->string('timezone', 60)->default('America/Sao_Paulo');
                $table->boolean('is_active')->default(true);
                $table->json('metadata_json')->nullable();
                $table->timestamps();

                $table->unique(['tenant_id', 'provider', 'external_account_id'], 'uq_ads_account');
                $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            });
        }

        // 3. Catálogos de produtos/imóveis
        if (!Schema::hasTable('ads_catalogs')) {
            Schema::create('ads_catalogs', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('tenant_id')->index();
                $table->string('provider', 20);
                $table->string('external_catalog_id', 100);
                $table->string('name', 255)->nullable();
                $table->string('status', 20)->default('ACTIVE'); // ACTIVE|PAUSED|ERROR
                $table->integer('items_count')->default(0);
                $table->timestamp('last_sync_at')->nullable();
                $table->json('metadata_json')->nullable();
                $table->timestamps();

                $table->unique(['tenant_id', 'provider', 'external_catalog_id'], 'uq_ads_catalog');
                $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            });
        }

        // 4. Sincronização de imóveis com os catálogos dos providers
        if (!Schema::hasTable('ads_listings')) {
            Schema::create('ads_listings', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('tenant_id')->index();
                $table->unsignedBigInteger('listing_id')->index(); // FK para imo_properties.id
                $table->string('provider', 20);
                $table->string('external_item_id', 150)->nullable(); // ID no catálogo do provider
                $table->string('external_catalog_id', 100)->nullable();
                $table->string('publish_status', 20)->default('DRAFT'); // DRAFT|PUBLISHING|ACTIVE|PAUSED|ERROR
                $table->timestamp('last_sync_at')->nullable();
                $table->text('last_error')->nullable();
                $table->integer('sync_attempts')->default(0);
                $table->json('metadata_json')->nullable();
                $table->timestamps();

                $table->unique(['tenant_id', 'listing_id', 'provider'], 'uq_ads_listing_provider');
                $table->index(['tenant_id', 'publish_status']);
                $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            });
        }

        // 5. Campanhas de anúncio (uma por tenant/provider/objetivo)
        if (!Schema::hasTable('ads_campaigns')) {
            Schema::create('ads_campaigns', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('tenant_id')->index();
                $table->string('provider', 20);
                $table->string('external_campaign_id', 100)->nullable();
                $table->string('external_adset_id', 100)->nullable();   // adset padrão
                $table->string('objective', 50)->default('LEAD_GENERATION'); // LEAD_GENERATION|CATALOG_SALES
                $table->string('status', 20)->default('DRAFT');  // DRAFT|ACTIVE|PAUSED|ERROR
                $table->integer('budget_daily_cents')->default(0); // orçamento em centavos
                $table->string('region', 255)->nullable();        // município/bairro/geo alvo
                $table->double('geo_lat')->nullable();
                $table->double('geo_lng')->nullable();
                $table->integer('geo_radius_km')->default(20);
                $table->json('metadata_json')->nullable();
                $table->timestamp('last_reconciled_at')->nullable();
                $table->timestamps();

                $table->unique(['tenant_id', 'provider', 'objective'], 'uq_ads_campaign_obj');
                $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            });
        }

        // 6. Entitlements (planos contratados por tenant)
        if (!Schema::hasTable('ads_entitlements')) {
            Schema::create('ads_entitlements', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('tenant_id')->index();
                $table->string('plan_code', 30); // ADS_BASIC, ADS_PRO, ADS_ENTERPRISE
                $table->json('providers_allowed')->nullable(); // ['meta', 'google']
                $table->integer('max_listings_per_day')->default(10);
                $table->integer('max_budget_daily_cents')->default(10000); // R$100 padrão
                $table->json('regions_allowed')->nullable();
                $table->boolean('remarketing_enabled')->default(false);
                $table->boolean('capi_enabled')->default(false);
                $table->boolean('multi_account_enabled')->default(false);
                $table->timestamp('valid_from')->nullable();
                $table->timestamp('valid_until')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->unique(['tenant_id', 'plan_code'], 'uq_ads_entitlement');
                $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            });
        }

        // 7. Subscriptions de Webhook
        if (!Schema::hasTable('ads_webhooks')) {
            Schema::create('ads_webhooks', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('tenant_id')->index();
                $table->string('provider', 20);
                $table->string('external_subscription_id', 150)->nullable();
                $table->string('external_page_id', 100)->nullable();     // Meta: page_id para roteamento
                $table->string('external_form_id', 100)->nullable();    // Meta: leadgen_form_id
                $table->string('status', 20)->default('INACTIVE');      // ACTIVE|INACTIVE|ERROR
                $table->string('verify_token_enc', 255)->nullable();    // token de verificação criptografado
                $table->timestamp('last_verified_at')->nullable();
                $table->timestamp('last_event_at')->nullable();
                $table->json('metadata_json')->nullable();
                $table->timestamps();

                $table->index(['tenant_id', 'provider']);
                $table->index(['provider', 'external_page_id']); // para roteamento por page_id
                $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            });
        }

        // 8. Leads captados pelos provedores
        if (!Schema::hasTable('ads_leads')) {
            Schema::create('ads_leads', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('tenant_id')->index();
                $table->string('provider', 20);
                $table->string('external_lead_id', 150);           // ID do lead no provider
                $table->unsignedBigInteger('listing_id')->nullable()->index();  // imóvel relacionado
                $table->unsignedBigInteger('contact_id')->nullable()->index();  // Pessoa no CRM
                $table->unsignedBigInteger('crm_lead_id')->nullable()->index(); // Lead no CRM
                $table->string('external_campaign_id', 100)->nullable();
                $table->string('external_adset_id', 100)->nullable();
                $table->string('external_ad_id', 100)->nullable();
                $table->string('external_form_id', 100)->nullable();
                $table->string('gclid', 200)->nullable();  // Google Click ID (rastreamento)
                $table->json('raw_payload_json')->nullable();       // payload original do provider
                $table->json('normalized_json')->nullable();        // { nome, email, telefone, mensagem }
                $table->boolean('is_duplicate')->default(false);
                $table->timestamp('received_at')->nullable();
                $table->timestamps();

                $table->unique(['tenant_id', 'provider', 'external_lead_id'], 'uq_ads_lead');
                $table->index(['tenant_id', 'provider', 'created_at']);
                $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            });
        }

        // 9. Audit log — rastreabilidade completa
        if (!Schema::hasTable('ads_audit_logs')) {
            Schema::create('ads_audit_logs', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('tenant_id')->index();
                $table->string('provider', 20)->nullable();
                $table->string('entity_type', 50)->nullable(); // connection|listing|campaign|webhook|lead
                $table->unsignedBigInteger('entity_id')->nullable();
                $table->string('action', 80);    // PUBLISH_REQUESTED|CATALOG_UPSERT|LEAD_RECEIVED etc
                $table->string('status', 20);    // SUCCESS|ERROR|SKIPPED
                $table->string('request_id', 64)->nullable();  // UUID único por operação
                $table->text('message')->nullable();
                $table->json('payload_json_sanitized')->nullable(); // NUNCA logar tokens ou secrets
                $table->string('http_status', 10)->nullable();
                $table->integer('duration_ms')->nullable();
                $table->timestamps();

                $table->index(['tenant_id', 'provider', 'created_at']);
                $table->index(['tenant_id', 'entity_type', 'entity_id']);
                $table->index(['tenant_id', 'action', 'status']);
            });
        }
    }

    public function down(): void
    {
        // Ordem inversa para respeitar FKs
        Schema::dropIfExists('ads_audit_logs');
        Schema::dropIfExists('ads_leads');
        Schema::dropIfExists('ads_webhooks');
        Schema::dropIfExists('ads_entitlements');
        Schema::dropIfExists('ads_campaigns');
        Schema::dropIfExists('ads_listings');
        Schema::dropIfExists('ads_catalogs');
        Schema::dropIfExists('ads_accounts');
        Schema::dropIfExists('ads_connections');
    }
};
