<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\RapCategory;
use App\Models\RapItem;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TransactionRapItemTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Project $project;
    private RapItem $rapItem;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user    = User::factory()->create();
        $this->project = Project::create(['name' => 'Test Project', 'status' => 'active']);

        $category = RapCategory::create([
            'project_id' => $this->project->id,
            'name'       => 'Pekerjaan Sipil',
            'sort_order' => 0,
        ]);

        $this->rapItem = RapItem::create([
            'category_id' => $category->id,
            'description' => 'Galian Tanah',
            'volume'      => 10,
            'unit'        => 'm3',
            'unit_price'  => 50_000,
            'sort_order'  => 0,
        ]);
    }

    /**
     * Transaksi bisa dibuat dengan rap_item_id — harus simpan dan response menyertakan relasi.
     */
    public function test_store_nested_transaction_with_rap_item_id(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson("/api/projects/{$this->project->id}/transactions", [
                'date'           => now()->toDateString(),
                'company'        => 'Vendor Galian',
                'description'    => 'Bayar Galian Tanah',
                'payment_method' => 'cash',
                'expense'        => 200_000,
                'income'         => 0,
                'rap_item_id'    => $this->rapItem->id,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('rap_item_id', $this->rapItem->id)
            ->assertJsonPath('rap_item.id', $this->rapItem->id)
            ->assertJsonPath('rap_item.description', 'Galian Tanah');

        $this->assertDatabaseHas('transactions', [
            'rap_item_id' => $this->rapItem->id,
            'expense'     => 200_000,
        ]);
    }

    /**
     * Transaksi tanpa rap_item_id tetap bisa dibuat (field opsional).
     */
    public function test_store_nested_transaction_without_rap_item_id_is_valid(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson("/api/projects/{$this->project->id}/transactions", [
                'date'           => now()->toDateString(),
                'company'        => 'Vendor Umum',
                'description'    => 'Biaya Operasional Umum',
                'payment_method' => 'cash',
                'expense'        => 100_000,
                'income'         => 0,
            ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('transactions', [
            'rap_item_id' => null,
            'expense'     => 100_000,
        ]);
    }

    /**
     * Update transaksi bisa menambahkan/mengubah rap_item_id.
     */
    public function test_update_transaction_can_set_rap_item_id(): void
    {
        // Buat transaksi tanpa rap_item_id dulu
        $trx = Transaction::create([
            'project_id'     => $this->project->id,
            'company'        => 'Vendor Lama',
            'date'           => now()->toDateString(),
            'description'    => 'Transaksi Lama',
            'payment_method' => 'cash',
            'expense'        => 50_000,
            'income'         => 0,
        ]);

        // Update dengan menambahkan rap_item_id
        $response = $this->actingAs($this->user)
            ->putJson("/api/transactions/{$trx->id}", [
                'rap_item_id' => $this->rapItem->id,
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('rap_item_id', $this->rapItem->id)
            ->assertJsonPath('rap_item.id', $this->rapItem->id);

        $this->assertDatabaseHas('transactions', [
            'id'          => $trx->id,
            'rap_item_id' => $this->rapItem->id,
        ]);
    }

    /**
     * Update transaksi bisa menghapus rap_item_id (set null).
     */
    public function test_update_transaction_can_clear_rap_item_id(): void
    {
        $trx = Transaction::create([
            'project_id'     => $this->project->id,
            'rap_item_id'    => $this->rapItem->id,
            'company'        => 'Vendor RAP',
            'date'           => now()->toDateString(),
            'description'    => 'Transaksi Dengan RAP',
            'payment_method' => 'cash',
            'expense'        => 50_000,
            'income'         => 0,
        ]);

        $response = $this->actingAs($this->user)
            ->putJson("/api/transactions/{$trx->id}", [
                'rap_item_id' => null,
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('rap_item_id', null);

        $this->assertDatabaseHas('transactions', [
            'id'          => $trx->id,
            'rap_item_id' => null,
        ]);
    }

    /**
     * Transaksi dengan rap_item_id yang ter-tag harus muncul di realisasi Laba/Rugi.
     */
    public function test_tagged_transaction_appears_in_laba_rugi_realisasi(): void
    {
        Transaction::create([
            'project_id'     => $this->project->id,
            'rap_item_id'    => $this->rapItem->id,
            'company'        => 'Vendor Galian',
            'date'           => now()->toDateString(),
            'description'    => 'Bayar Galian',
            'payment_method' => 'cash',
            'expense'        => 300_000,
            'income'         => 0,
        ]);

        $data = $this->actingAs($this->user)
            ->getJson("/api/projects/{$this->project->id}/laba-rugi")
            ->assertOk()
            ->json();

        $item = collect($data['items'])->firstWhere('id', $this->rapItem->id);

        $this->assertNotNull($item, 'RAP item harus muncul di response laba-rugi');
        // rencana = 10 * 50000 = 500000
        $this->assertEquals(500_000.0, $item['total_price']);
        // realisasi = transaksi yg ter-tag = 300000
        $this->assertEquals(300_000.0, $item['total_realisasi']);
        // selisih = 500000 - 300000 = 200000 (untung)
        $this->assertEquals(200_000.0, $item['selisih_laba_rugi']);
        $this->assertEquals('untung', $item['status_label']);
    }

    /**
     * Endpoint GET /projects/{project}/rap-items mengembalikan flat list yang benar.
     */
    public function test_rap_items_flat_list_endpoint_returns_items_for_project(): void
    {
        // Item milik project ini sudah dibuat di setUp (rapItem)
        // Tambahkan 1 item di project lain — tidak boleh ikut muncul
        $otherProject  = Project::create(['name' => 'Other Project', 'status' => 'active']);
        $otherCategory = RapCategory::create([
            'project_id' => $otherProject->id,
            'name'       => 'Kategori Lain',
            'sort_order' => 0,
        ]);
        RapItem::create([
            'category_id' => $otherCategory->id,
            'description' => 'Item Project Lain',
            'volume'      => 1,
            'unit'        => 'ls',
            'unit_price'  => 100_000,
            'sort_order'  => 0,
        ]);

        $data = $this->actingAs($this->user)
            ->getJson("/api/projects/{$this->project->id}/rap-items")
            ->assertOk()
            ->json();

        // Hanya 1 item milik project ini
        $this->assertCount(1, $data);
        $this->assertEquals($this->rapItem->id, $data[0]['id']);
        $this->assertEquals('Galian Tanah', $data[0]['description']);

        // Pastikan item project lain tidak ikut
        $ids = array_column($data, 'id');
        $this->assertNotContains($otherCategory->id, $ids);
    }

    /**
     * Endpoint rap-items mengembalikan array kosong kalau project belum punya RAP.
     */
    public function test_rap_items_flat_list_returns_empty_for_project_without_rap(): void
    {
        $emptyProject = Project::create(['name' => 'Empty Project', 'status' => 'active']);

        $data = $this->actingAs($this->user)
            ->getJson("/api/projects/{$emptyProject->id}/rap-items")
            ->assertOk()
            ->json();

        $this->assertIsArray($data);
        $this->assertCount(0, $data);
    }
}
