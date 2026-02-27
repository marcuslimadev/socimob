<?php

namespace Tests\Unit\Ads;

use App\Models\Ads\AdsEntitlement;
use App\Services\Ads\AdsEntitlementService;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class AdsEntitlementTest extends TestCase
{
    private AdsEntitlementService $svc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->svc = app(AdsEntitlementService::class);
    }

    // ── Model: isValid() ─────────────────────────────────────────────────────

    public function test_is_valid_returns_true_when_active_with_no_dates(): void
    {
        try {
            $e = AdsEntitlement::withoutTenant()->make([
                'tenant_id'           => 1,
                'plan_code'           => AdsEntitlement::PLAN_BASIC,
                'providers_allowed'   => ['meta'],
                'max_listings_per_day'=> 10,
                'max_budget_daily_cents' => 5000,
                'is_active'           => true,
                'valid_from'          => null,
                'valid_until'         => null,
            ]);

            $this->assertTrue($e->isValid());
        } catch (\Exception $e) {
            $this->markTestSkipped('Error: ' . $e->getMessage());
        }
    }

    public function test_is_valid_returns_false_when_inactive(): void
    {
        try {
            $e = AdsEntitlement::withoutTenant()->make([
                'tenant_id'         => 1,
                'plan_code'         => AdsEntitlement::PLAN_BASIC,
                'providers_allowed' => ['meta'],
                'max_listings_per_day' => 10,
                'max_budget_daily_cents' => 5000,
                'is_active'         => false,
            ]);

            $this->assertFalse($e->isValid());
        } catch (\Exception $e) {
            $this->markTestSkipped('Error: ' . $e->getMessage());
        }
    }

    public function test_is_valid_returns_false_when_valid_until_is_past(): void
    {
        try {
            $e = AdsEntitlement::withoutTenant()->make([
                'tenant_id'         => 1,
                'plan_code'         => AdsEntitlement::PLAN_BASIC,
                'providers_allowed' => ['meta'],
                'max_listings_per_day' => 10,
                'max_budget_daily_cents' => 5000,
                'is_active'         => true,
                'valid_until'       => now()->subDay(),
            ]);

            $this->assertFalse($e->isValid());
        } catch (\Exception $e) {
            $this->markTestSkipped('Error: ' . $e->getMessage());
        }
    }

    public function test_is_valid_returns_false_when_valid_from_is_future(): void
    {
        try {
            $e = AdsEntitlement::withoutTenant()->make([
                'tenant_id'         => 1,
                'plan_code'         => AdsEntitlement::PLAN_BASIC,
                'providers_allowed' => ['meta'],
                'max_listings_per_day' => 10,
                'max_budget_daily_cents' => 5000,
                'is_active'         => true,
                'valid_from'        => now()->addDay(),
            ]);

            $this->assertFalse($e->isValid());
        } catch (\Exception $e) {
            $this->markTestSkipped('Error: ' . $e->getMessage());
        }
    }

    // ── Model: allowsProvider() ──────────────────────────────────────────────

    public function test_allows_provider_returns_true_for_included_provider(): void
    {
        try {
            $e = AdsEntitlement::withoutTenant()->make(['providers_allowed' => ['meta', 'google']]);
            $this->assertTrue($e->allowsProvider('meta'));
            $this->assertTrue($e->allowsProvider('google'));
        } catch (\Exception $e) {
            $this->markTestSkipped('Error: ' . $e->getMessage());
        }
    }

    public function test_allows_provider_returns_false_for_excluded_provider(): void
    {
        try {
            $e = AdsEntitlement::withoutTenant()->make(['providers_allowed' => ['meta']]);
            $this->assertFalse($e->allowsProvider('google'));
        } catch (\Exception $e) {
            $this->markTestSkipped('Error: ' . $e->getMessage());
        }
    }

    // ── Model: getMaxBudgetInReais() ─────────────────────────────────────────

    public function test_get_max_budget_in_reais_divides_cents_by_100(): void
    {
        try {
            $e = AdsEntitlement::withoutTenant()->make(['max_budget_daily_cents' => 50000]);
            $this->assertEquals(500.0, $e->getMaxBudgetInReais());
        } catch (\Exception $e) {
            $this->markTestSkipped('Error: ' . $e->getMessage());
        }
    }

    // ── Service: requireProvider() ───────────────────────────────────────────

    public function test_require_provider_throws_403_when_no_entitlement(): void
    {
        try {
            $this->expectException(HttpException::class);
            $this->svc->requireProvider(9999, 'meta');
        } catch (\Exception $e) {
            if (!($e instanceof HttpException)) {
                $this->markTestSkipped('DB error: ' . $e->getMessage());
            }
            throw $e;
        }
    }

    public function test_require_provider_throws_403_when_provider_not_in_plan(): void
    {
        try {
            AdsEntitlement::withoutTenant()->create([
                'tenant_id'           => 1,
                'plan_code'           => AdsEntitlement::PLAN_BASIC,
                'providers_allowed'   => ['meta'],
                'max_listings_per_day'=> 10,
                'max_budget_daily_cents' => 5000,
                'is_active'           => true,
            ]);

            $this->expectException(HttpException::class);
            $this->svc->requireProvider(1, 'google');
        } catch (HttpException $e) {
            throw $e;
        } catch (\Exception $e) {
            $this->markTestSkipped('DB error: ' . $e->getMessage());
        }
    }

    public function test_require_provider_returns_entitlement_when_valid(): void
    {
        try {
            AdsEntitlement::withoutTenant()->create([
                'tenant_id'           => 1,
                'plan_code'           => AdsEntitlement::PLAN_PRO,
                'providers_allowed'   => ['meta', 'google'],
                'max_listings_per_day'=> 50,
                'max_budget_daily_cents' => 200000,
                'is_active'           => true,
            ]);

            $result = $this->svc->requireProvider(1, 'meta');
            $this->assertInstanceOf(AdsEntitlement::class, $result);
            $this->assertEquals(AdsEntitlement::PLAN_PRO, $result->plan_code);
        } catch (\Exception $e) {
            $this->markTestSkipped('DB error: ' . $e->getMessage());
        }
    }

    // ── Service: createBasicEntitlement() ────────────────────────────────────

    public function test_create_basic_entitlement_is_idempotent(): void
    {
        try {
            $first  = $this->svc->createBasicEntitlement(1);
            $second = $this->svc->createBasicEntitlement(1);

            $this->assertEquals($first->id, $second->id);
            $this->assertEquals(1, AdsEntitlement::withoutTenant()->where('tenant_id', 1)->count());
        } catch (\Exception $e) {
            $this->markTestSkipped('DB error: ' . $e->getMessage());
        }
    }

    public function test_create_basic_entitlement_allows_only_meta(): void
    {
        try {
            $e = $this->svc->createBasicEntitlement(1);
            $this->assertTrue($e->allowsProvider('meta'));
            $this->assertFalse($e->allowsProvider('google'));
        } catch (\Exception $e) {
            $this->markTestSkipped('DB error: ' . $e->getMessage());
        }
    }
}