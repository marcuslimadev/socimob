CREATE TABLE IF NOT EXISTS `ads_connections` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) UNSIGNED NOT NULL,
  `provider` varchar(20) NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'DRAFT',
  `token_enc` text DEFAULT NULL,
  `refresh_token_enc` text DEFAULT NULL,
  `scopes` text DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `external_user_id` varchar(100) DEFAULT NULL,
  `external_business_id` varchar(100) DEFAULT NULL,
  `metadata_json` longtext DEFAULT NULL,
  `last_refresh_at` timestamp NULL DEFAULT NULL,
  `disconnected_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ads_conn_tenant_provider` (`tenant_id`,`provider`),
  KEY `ads_connections_tenant_id_index` (`tenant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `ads_accounts` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) UNSIGNED NOT NULL,
  `provider` varchar(20) NOT NULL,
  `external_account_id` varchar(100) NOT NULL,
  `name` varchar(255) DEFAULT NULL,
  `currency` varchar(10) NOT NULL DEFAULT 'BRL',
  `timezone` varchar(60) NOT NULL DEFAULT 'America/Sao_Paulo',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `metadata_json` longtext DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ads_account` (`tenant_id`,`provider`,`external_account_id`),
  KEY `ads_accounts_tenant_id_index` (`tenant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `ads_catalogs` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) UNSIGNED NOT NULL,
  `provider` varchar(20) NOT NULL,
  `external_catalog_id` varchar(100) NOT NULL,
  `name` varchar(255) DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'ACTIVE',
  `items_count` int(11) NOT NULL DEFAULT 0,
  `last_sync_at` timestamp NULL DEFAULT NULL,
  `metadata_json` longtext DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ads_catalog` (`tenant_id`,`provider`,`external_catalog_id`),
  KEY `ads_catalogs_tenant_id_index` (`tenant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `ads_listings` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) UNSIGNED NOT NULL,
  `listing_id` bigint(20) UNSIGNED NOT NULL,
  `provider` varchar(20) NOT NULL,
  `external_item_id` varchar(150) DEFAULT NULL,
  `external_catalog_id` varchar(100) DEFAULT NULL,
  `publish_status` varchar(20) NOT NULL DEFAULT 'DRAFT',
  `last_sync_at` timestamp NULL DEFAULT NULL,
  `last_error` text DEFAULT NULL,
  `sync_attempts` int(11) NOT NULL DEFAULT 0,
  `metadata_json` longtext DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ads_listing_provider` (`tenant_id`,`listing_id`,`provider`),
  KEY `ads_listings_tenant_id_index` (`tenant_id`),
  KEY `ads_listings_listing_id_index` (`listing_id`),
  KEY `ads_listings_tenant_publish_index` (`tenant_id`,`publish_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `ads_campaigns` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) UNSIGNED NOT NULL,
  `provider` varchar(20) NOT NULL,
  `external_campaign_id` varchar(100) DEFAULT NULL,
  `external_adset_id` varchar(100) DEFAULT NULL,
  `objective` varchar(50) NOT NULL DEFAULT 'LEAD_GENERATION',
  `status` varchar(20) NOT NULL DEFAULT 'DRAFT',
  `budget_daily_cents` int(11) NOT NULL DEFAULT 0,
  `region` varchar(255) DEFAULT NULL,
  `geo_lat` double DEFAULT NULL,
  `geo_lng` double DEFAULT NULL,
  `geo_radius_km` int(11) NOT NULL DEFAULT 20,
  `metadata_json` longtext DEFAULT NULL,
  `last_reconciled_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ads_campaign_obj` (`tenant_id`,`provider`,`objective`),
  KEY `ads_campaigns_tenant_id_index` (`tenant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `ads_entitlements` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) UNSIGNED NOT NULL,
  `plan_code` varchar(30) NOT NULL,
  `providers_allowed` longtext DEFAULT NULL,
  `max_listings_per_day` int(11) NOT NULL DEFAULT 10,
  `max_budget_daily_cents` int(11) NOT NULL DEFAULT 10000,
  `regions_allowed` longtext DEFAULT NULL,
  `remarketing_enabled` tinyint(1) NOT NULL DEFAULT 0,
  `capi_enabled` tinyint(1) NOT NULL DEFAULT 0,
  `multi_account_enabled` tinyint(1) NOT NULL DEFAULT 0,
  `valid_from` timestamp NULL DEFAULT NULL,
  `valid_until` timestamp NULL DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ads_entitlement` (`tenant_id`,`plan_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `ads_webhooks` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) UNSIGNED NOT NULL,
  `provider` varchar(20) NOT NULL,
  `external_subscription_id` varchar(150) DEFAULT NULL,
  `external_page_id` varchar(100) DEFAULT NULL,
  `external_form_id` varchar(100) DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'INACTIVE',
  `verify_token_enc` varchar(255) DEFAULT NULL,
  `last_verified_at` timestamp NULL DEFAULT NULL,
  `last_event_at` timestamp NULL DEFAULT NULL,
  `metadata_json` longtext DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `ads_webhooks_tenant_id_index` (`tenant_id`),
  KEY `ads_webhooks_tenant_provider_index` (`tenant_id`,`provider`),
  KEY `ads_webhooks_provider_page_index` (`provider`,`external_page_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `ads_leads` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) UNSIGNED NOT NULL,
  `provider` varchar(20) NOT NULL,
  `external_lead_id` varchar(150) NOT NULL,
  `listing_id` bigint(20) UNSIGNED DEFAULT NULL,
  `contact_id` bigint(20) UNSIGNED DEFAULT NULL,
  `crm_lead_id` bigint(20) UNSIGNED DEFAULT NULL,
  `external_campaign_id` varchar(100) DEFAULT NULL,
  `external_adset_id` varchar(100) DEFAULT NULL,
  `external_ad_id` varchar(100) DEFAULT NULL,
  `external_form_id` varchar(100) DEFAULT NULL,
  `gclid` varchar(200) DEFAULT NULL,
  `raw_payload_json` longtext DEFAULT NULL,
  `normalized_json` longtext DEFAULT NULL,
  `is_duplicate` tinyint(1) NOT NULL DEFAULT 0,
  `received_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ads_lead` (`tenant_id`,`provider`,`external_lead_id`),
  KEY `ads_leads_tenant_id_index` (`tenant_id`),
  KEY `ads_leads_listing_id_index` (`listing_id`),
  KEY `ads_leads_contact_id_index` (`contact_id`),
  KEY `ads_leads_crm_lead_id_index` (`crm_lead_id`),
  KEY `ads_leads_timeline_index` (`tenant_id`,`provider`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `ads_audit_logs` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) UNSIGNED NOT NULL,
  `provider` varchar(20) DEFAULT NULL,
  `entity_type` varchar(50) DEFAULT NULL,
  `entity_id` bigint(20) UNSIGNED DEFAULT NULL,
  `action` varchar(80) NOT NULL,
  `status` varchar(20) NOT NULL,
  `request_id` varchar(64) DEFAULT NULL,
  `message` text DEFAULT NULL,
  `payload_json_sanitized` longtext DEFAULT NULL,
  `http_status` varchar(10) DEFAULT NULL,
  `duration_ms` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `ads_audit_timeline_index` (`tenant_id`,`provider`,`created_at`),
  KEY `ads_audit_entity_index` (`tenant_id`,`entity_type`,`entity_id`),
  KEY `ads_audit_action_index` (`tenant_id`,`action`,`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO ads_accounts (tenant_id, provider, external_account_id, name, currency, timezone, is_active, metadata_json, created_at, updated_at)
VALUES (1, 'meta', 'act_137441020060069', 'Exclusiva Lar Imoveis - Meta Ads', 'BRL', 'America/Sao_Paulo', 1, '{"source":"manual_setup"}', NOW(), NOW())
ON DUPLICATE KEY UPDATE name='Exclusiva Lar Imoveis - Meta Ads', is_active=1, updated_at=NOW();

INSERT INTO ads_entitlements (tenant_id, plan_code, providers_allowed, max_listings_per_day, max_budget_daily_cents, remarketing_enabled, capi_enabled, multi_account_enabled, is_active, valid_from, created_at, updated_at)
VALUES (1, 'ADS_BASIC', '["meta"]', 50, 50000, 0, 0, 0, 1, NOW(), NOW(), NOW())
ON DUPLICATE KEY UPDATE providers_allowed='["meta"]', is_active=1, updated_at=NOW();

SELECT 'ads_accounts' AS tabela, external_account_id, name, is_active FROM ads_accounts WHERE tenant_id=1 AND provider='meta';
SELECT 'ads_entitlements' AS tabela, plan_code, providers_allowed, is_active FROM ads_entitlements WHERE tenant_id=1;
SELECT 'tabelas_criadas' AS info, COUNT(*) AS total FROM information_schema.tables WHERE table_schema='u815655858_saas' AND table_name LIKE 'ads_%';
