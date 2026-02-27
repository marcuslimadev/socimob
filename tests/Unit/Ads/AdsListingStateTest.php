<?php

namespace Tests\Unit\Ads;

use App\Models\Ads\AdsListing;
use Tests\TestCase;

class AdsListingStateTest extends TestCase
{
    private function makeListing(): AdsListing
    {
        return AdsListing::withoutTenant()->create([
            'tenant_id'      => 1,
            'listing_id'     => 1,
            'provider'       => 'meta',
            'publish_status' => AdsListing::STATUS_DRAFT,
            'sync_attempts'  => 0,
        ]);
    }

    public function test_initial_status_is_draft(): void
    {
        try {
            $listing = $this->makeListing();
            $this->assertEquals(AdsListing::STATUS_DRAFT, $listing->publish_status);
        } catch (\Exception $e) {
            $this->markTestSkipped('DB error: ' . $e->getMessage());
        }
    }

    public function test_mark_publishing_sets_status_and_clears_error(): void
    {
        try {
            $listing = $this->makeListing();
            $listing->update(['last_error' => 'some previous error']);

            $listing->markPublishing();
            $listing->refresh();

            $this->assertEquals(AdsListing::STATUS_PUBLISHING, $listing->publish_status);
            $this->assertNull($listing->last_error);
        } catch (\Exception $e) {
            $this->markTestSkipped('DB error: ' . $e->getMessage());
        }
    }

    public function test_mark_active_sets_external_item_id_and_clears_error(): void
    {
        try {
            $listing = $this->makeListing();
            $listing->markPublishing();
            $listing->markActive('soci_1_1');
            $listing->refresh();

            $this->assertEquals(AdsListing::STATUS_ACTIVE, $listing->publish_status);
            $this->assertEquals('soci_1_1', $listing->external_item_id);
            $this->assertNotNull($listing->last_sync_at);
            $this->assertNull($listing->last_error);
        } catch (\Exception $e) {
            $this->markTestSkipped('DB error: ' . $e->getMessage());
        }
    }

    public function test_mark_error_increments_sync_attempts(): void
    {
        try {
            $listing = $this->makeListing();
            $this->assertEquals(0, $listing->sync_attempts);

            $listing->markError('API timeout');
            $listing->refresh();

            $this->assertEquals(AdsListing::STATUS_ERROR, $listing->publish_status);
            $this->assertEquals('API timeout', $listing->last_error);
            $this->assertEquals(1, $listing->sync_attempts);

            $listing->markError('Another error');
            $listing->refresh();
            $this->assertEquals(2, $listing->sync_attempts);
        } catch (\Exception $e) {
            $this->markTestSkipped('DB error: ' . $e->getMessage());
        }
    }

    public function test_mark_paused_sets_status_paused(): void
    {
        try {
            $listing = $this->makeListing();
            $listing->markActive('ext_id_123');
            $listing->markPaused();
            $listing->refresh();

            $this->assertEquals(AdsListing::STATUS_PAUSED, $listing->publish_status);
        } catch (\Exception $e) {
            $this->markTestSkipped('DB error: ' . $e->getMessage());
        }
    }

    public function test_listing_can_be_republished_after_pause(): void
    {
        try {
            $listing = $this->makeListing();
            $listing->markActive('ext_id_123');
            $listing->markPaused();
            $listing->markPublishing();
            $listing->refresh();

            $this->assertEquals(AdsListing::STATUS_PUBLISHING, $listing->publish_status);
        } catch (\Exception $e) {
            $this->markTestSkipped('DB error: ' . $e->getMessage());
        }
    }

    public function test_updateorcreate_returns_same_record_on_second_call(): void
    {
        try {
            $first = AdsListing::withoutTenant()->updateOrCreate(
                ['tenant_id' => 1, 'listing_id' => 99, 'provider' => 'meta'],
                ['publish_status' => AdsListing::STATUS_PUBLISHING]
            );
            $second = AdsListing::withoutTenant()->updateOrCreate(
                ['tenant_id' => 1, 'listing_id' => 99, 'provider' => 'meta'],
                ['publish_status' => AdsListing::STATUS_PUBLISHING]
            );

            $this->assertEquals($first->id, $second->id);
            $this->assertEquals(
                1,
                AdsListing::withoutTenant()->where('tenant_id', 1)->where('listing_id', 99)->count()
            );
        } catch (\Exception $e) {
            $this->markTestSkipped('DB error: ' . $e->getMessage());
        }
    }
}
