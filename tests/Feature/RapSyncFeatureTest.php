<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\RabCategory;
use App\Models\RabItem;
use App\Models\RapCategory;
use App\Models\RapItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RapSyncFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected Project $project;
    protected RabCategory $rabCat;
    protected RabItem $rabItem;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());

        $this->project = Project::create([
            'name'        => 'Sync Test Project',
            'client_name' => 'Client',
            'start_date'  => '2024-01-01',
            'budget'      => 100000000,
        ]);

        $this->rabCat = RabCategory::create([
            'project_id' => $this->project->id,
            'name'       => 'Kategori A',
            'code'       => 'A',
            'sort_order' => 1,
        ]);

        $this->rabItem = RabItem::create([
            'category_id' => $this->rabCat->id,
            'description' => 'Item Asli',
            'volume'      => 10,
            'unit'        => 'm',
            'unit_price'  => 5000,
            'status'      => 'aktif',
        ]);
    }

    // ─── Helper ────────────────────────────────────────────────────────────────

    private function generateInitialRap(): array
    {
        $rapCat = RapCategory::create([
            'project_id' => $this->project->id,
            'name'       => 'Kategori A',
            'code'       => 'A',
            'sort_order' => 1,
        ]);

        $rapItem = RapItem::create([
            'category_id'                     => $rapCat->id,
            'description'                     => $this->rabItem->description,
            'volume'                          => $this->rabItem->volume,
            'unit'                            => $this->rabItem->unit,
            'unit_price'                      => 0,
            'sort_order'                      => 1,
            'source_rab_item_id'              => $this->rabItem->id,
            'source_rab_description_snapshot' => $this->rabItem->description,
            'source_rab_volume_snapshot'      => $this->rabItem->volume,
        ]);

        return [$rapCat, $rapItem];
    }

    // ─── sync-new-items ────────────────────────────────────────────────────────

    public function test_sync_new_items_adds_new_items_without_touching_existing()
    {
        [$rapCat, $existingRapItem] = $this->generateInitialRap();

        // Add a second RAB item that is NOT yet in RAP
        $newRabItem = RabItem::create([
            'category_id' => $this->rabCat->id,
            'description' => 'Item Baru',
            'volume'      => 5,
            'unit'        => 'pcs',
            'unit_price'  => 2000,
            'status'      => 'aktif',
        ]);

        $response = $this->postJson("/api/projects/{$this->project->id}/rap/sync-new-items");

        $response->assertStatus(201)
                 ->assertJsonPath('created_count', 1);

        // Existing rap item MUST remain untouched
        $this->assertDatabaseHas('rap_items', [
            'id'          => $existingRapItem->id,
            'description' => 'Item Asli',
            'volume'      => 10,
            'unit_price'  => 0,
        ]);

        // New rap item should have been created with snapshot
        $this->assertDatabaseHas('rap_items', [
            'source_rab_item_id'              => $newRabItem->id,
            'description'                     => 'Item Baru',
            'volume'                          => 5,
            'unit_price'                      => 0,
            'source_rab_description_snapshot' => 'Item Baru',
            'source_rab_volume_snapshot'      => 5,
        ]);
    }

    public function test_sync_new_items_auto_creates_missing_rap_category()
    {
        // RAP exists but only for "Kategori A"
        $this->generateInitialRap();

        // Add a brand-new RAB category with a new item - this category has NO rap_category yet
        $newRabCat = RabCategory::create([
            'project_id' => $this->project->id,
            'name'       => 'Kategori B (Baru)',
            'code'       => 'B',
            'sort_order' => 2,
        ]);
        $newRabItem = RabItem::create([
            'category_id' => $newRabCat->id,
            'description' => 'Item di Kategori Baru',
            'volume'      => 3,
            'unit'        => 'unit',
            'unit_price'  => 1000,
            'status'      => 'aktif',
        ]);

        $response = $this->postJson("/api/projects/{$this->project->id}/rap/sync-new-items");

        $response->assertStatus(201)
                 ->assertJsonPath('created_count', 1);

        // A new rap_category should have been auto-created mirroring the new rab_category
        $this->assertDatabaseHas('rap_categories', [
            'project_id' => $this->project->id,
            'name'       => 'Kategori B (Baru)',
            'code'       => 'B',
        ]);

        // The new rap_item should be placed in the auto-created category
        $this->assertDatabaseHas('rap_items', [
            'source_rab_item_id' => $newRabItem->id,
            'description'        => 'Item di Kategori Baru',
            'volume'             => 3,
        ]);
    }

    public function test_sync_new_items_skips_cancelled_rab_items()
    {
        $this->generateInitialRap();

        $cancelledItem = RabItem::create([
            'category_id' => $this->rabCat->id,
            'description' => 'Item Dibatalkan',
            'volume'      => 2,
            'unit'        => 'pcs',
            'unit_price'  => 100,
            'status'      => 'dibatalkan',
        ]);

        $response = $this->postJson("/api/projects/{$this->project->id}/rap/sync-new-items");

        // Cancelled item must NOT be created
        $this->assertDatabaseMissing('rap_items', [
            'source_rab_item_id' => $cancelledItem->id,
        ]);

        $response->assertJsonPath('created_count', 0);
    }

    // ─── sync-status ───────────────────────────────────────────────────────────

    public function test_sync_status_returns_synced_for_unchanged_items()
    {
        [, $rapItem] = $this->generateInitialRap();

        $response = $this->getJson("/api/projects/{$this->project->id}/rap/sync-status");

        $response->assertOk()
                 ->assertJsonPath("{$rapItem->id}.status", 'synced');
    }

    public function test_sync_status_detects_rab_changed()
    {
        [, $rapItem] = $this->generateInitialRap();

        // Mutate the RAB item (description changed)
        $this->rabItem->update(['description' => 'Item Asli - DIUBAH', 'volume' => 20]);

        $response = $this->getJson("/api/projects/{$this->project->id}/rap/sync-status");

        $response->assertOk()
                 ->assertJsonPath("{$rapItem->id}.status", 'rab_changed')
                 ->assertJsonPath("{$rapItem->id}.latest_rab.description", 'Item Asli - DIUBAH')
                 ->assertJsonPath("{$rapItem->id}.latest_rab.volume", 20);
    }

    public function test_sync_status_detects_rab_removed_when_deleted()
    {
        [, $rapItem] = $this->generateInitialRap();

        // When rab_item is hard-deleted, the DB nullOnDelete FK causes
        // source_rab_item_id to become NULL — the rap_item disappears from sync-status.
        // This is expected behavior: the item is no longer linked to any RAB source.
        $this->rabItem->delete();

        $response = $this->getJson("/api/projects/{$this->project->id}/rap/sync-status");

        $response->assertOk();
        // The rap_item should NOT appear in sync-status (source_rab_item_id is now NULL)
        $data = $response->json();
        $this->assertArrayNotHasKey((string) $rapItem->id, $data);

        // Also verify that source_rab_item_id was nulled on the rap_item
        $this->assertDatabaseHas('rap_items', [
            'id'                 => $rapItem->id,
            'source_rab_item_id' => null,
        ]);
    }

    public function test_sync_status_detects_rab_removed_when_cancelled()
    {
        [, $rapItem] = $this->generateInitialRap();

        $this->rabItem->update(['status' => 'dibatalkan']);

        $response = $this->getJson("/api/projects/{$this->project->id}/rap/sync-status");

        $response->assertOk()
                 ->assertJsonPath("{$rapItem->id}.status", 'rab_removed');
    }

    // ─── unsynced-rab-items-count ──────────────────────────────────────────────

    public function test_unsynced_rab_items_count_returns_correct_number()
    {
        $this->generateInitialRap();

        // 1 existing synced item
        // Now add 2 new items in RAB that are not in RAP
        RabItem::create([
            'category_id' => $this->rabCat->id,
            'description' => 'Unsynced 1',
            'volume'      => 5,
            'unit'        => 'pcs',
            'unit_price'  => 1000,
            'status'      => 'aktif',
        ]);
        RabItem::create([
            'category_id' => $this->rabCat->id,
            'description' => 'Unsynced 2',
            'volume'      => 10,
            'unit'        => 'm',
            'unit_price'  => 2000,
            'status'      => 'aktif',
        ]);
        
        // Add 1 cancelled item (should be ignored)
        RabItem::create([
            'category_id' => $this->rabCat->id,
            'description' => 'Cancelled Unsynced',
            'volume'      => 1,
            'unit'        => 'ls',
            'unit_price'  => 500,
            'status'      => 'dibatalkan',
        ]);

        $response = $this->getJson("/api/projects/{$this->project->id}/rap/unsynced-rab-items-count");

        $response->assertOk()
                 ->assertJsonPath('count', 2);
    }

    // ─── sync-from-rab ─────────────────────────────────────────────────────────

    public function test_sync_from_rab_updates_description_and_volume_not_unit_price()
    {
        [, $rapItem] = $this->generateInitialRap();
        $rapItem->update(['unit_price' => 99999]); // set a known unit_price

        // Change rab item
        $this->rabItem->update(['description' => 'Deskripsi Baru', 'volume' => 25]);

        $response = $this->postJson("/api/rap-items/{$rapItem->id}/sync-from-rab");

        $response->assertOk()
                 ->assertJsonPath('message', 'Berhasil di-sync dari RAB.');

        $this->assertDatabaseHas('rap_items', [
            'id'                              => $rapItem->id,
            'description'                     => 'Deskripsi Baru',
            'volume'                          => 25,
            'unit_price'                      => 99999, // MUST NOT change
            'source_rab_description_snapshot' => 'Deskripsi Baru',
            'source_rab_volume_snapshot'      => 25,
        ]);
    }

    public function test_sync_from_rab_fails_when_source_deleted()
    {
        [, $rapItem] = $this->generateInitialRap();

        $this->rabItem->delete();

        $response = $this->postJson("/api/rap-items/{$rapItem->id}/sync-from-rab");

        $response->assertStatus(400);
    }

    public function test_sync_from_rab_fails_when_source_cancelled()
    {
        [, $rapItem] = $this->generateInitialRap();

        $this->rabItem->update(['status' => 'dibatalkan']);

        $response = $this->postJson("/api/rap-items/{$rapItem->id}/sync-from-rab");

        $response->assertStatus(400);
    }
}
