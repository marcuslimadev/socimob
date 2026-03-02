-- Setup Ads Automation — Exclusiva Lar Imoveis
-- Ad Account ID: act_137441020060069

INSERT INTO ads_accounts (tenant_id, provider, external_account_id, name, currency, timezone, is_active, metadata_json, created_at, updated_at)
VALUES (1, 'meta', 'act_137441020060069', 'Exclusiva Lar Imoveis - Meta Ads', 'BRL', 'America/Sao_Paulo', 1, '{"source":"manual_setup"}', NOW(), NOW())
ON DUPLICATE KEY UPDATE name='Exclusiva Lar Imoveis - Meta Ads', is_active=1, updated_at=NOW();

INSERT INTO ads_entitlements (tenant_id, plan_code, providers_allowed, max_listings_per_day, max_budget_daily_cents, remarketing_enabled, capi_enabled, multi_account_enabled, is_active, valid_from, created_at, updated_at)
VALUES (1, 'ADS_BASIC', '["meta"]', 50, 50000, 0, 0, 0, 1, NOW(), NOW(), NOW())
ON DUPLICATE KEY UPDATE providers_allowed='["meta"]', is_active=1, updated_at=NOW();

SELECT 'ads_accounts' AS tabela, external_account_id, name, is_active FROM ads_accounts WHERE tenant_id=1 AND provider='meta';
SELECT 'ads_entitlements' AS tabela, plan_code, providers_allowed, is_active FROM ads_entitlements WHERE tenant_id=1;
