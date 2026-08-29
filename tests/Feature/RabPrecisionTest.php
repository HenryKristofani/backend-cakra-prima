<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\RabCategory;
use App\Models\RabItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * @group pgsql
 *
 * Test ini HARUS dijalankan terhadap PostgreSQL karena menguji presisi
 * DECIMAL(24,10) yang tidak didukung oleh SQLite (SQLite menyimpan DECIMAL
 * sebagai REAL 64-bit yang memotong digit presisi tinggi).
 *
 * Jalankan dengan:
 *   $env:DB_CONNECTION="pgsql"; $env:DB_DATABASE="testing"; php artisan test --group=pgsql
 */
class RabPrecisionTest extends TestCase
{
    use RefreshDatabase;

    protected $user;
    protected $project;
    protected $category;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Test ini hanya valid di PostgreSQL — skip jika berjalan di SQLite
        if (config('database.default') === 'sqlite') {
            $this->markTestSkipped('RabPrecisionTest memerlukan PostgreSQL. Jalankan dengan DB_CONNECTION=pgsql.');
        }
        
        $this->user = User::factory()->create();
        $this->project = Project::create([
            'name' => 'Test Project',
            'code' => 'PRJ-TEST',
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
        ]);
        $this->category = RabCategory::create([
            'project_id' => $this->project->id,
            'name' => 'Persiapan',
        ]);
    }

    public function test_rab_item_precision_calculations()
    {
        // Skenario 1: Baris 148 (Harga asli: 929543.5293695611, Volume: 61). Ekspektasi: 56.702.155,29.
        $item1 = RabItem::create([
            'category_id' => $this->category->id,
            'description' => 'Test Presisi 1',
            'volume' => 61,
            'unit' => 'm3',
            'unit_price' => '929543.5293695611', // As string
            'status' => 'aktif',
        ]);
        $item1->refresh(); // Assert from DB persistence, not in-memory
        
        $this->assertEquals('56702155.2915432271', $item1->total_price);
        
        // Skenario 2: Volume besar dengan harga desimal kecil. (Contoh: 0.11111111 × 10000)
        $item2 = RabItem::create([
            'category_id' => $this->category->id,
            'description' => 'Test Presisi 2',
            'volume' => 10000,
            'unit' => 'ls',
            'unit_price' => '0.11111111',
            'status' => 'aktif',
        ]);
        $item2->refresh();
        
        $this->assertEquals('1111.1111000000', $item2->total_price);

        // Skenario 3: Harga satuan besar dengan desimal. (Contoh: 15000000.333333 × 2)
        $item3 = RabItem::create([
            'category_id' => $this->category->id,
            'description' => 'Test Presisi 3',
            'volume' => 2,
            'unit' => 'unit',
            'unit_price' => '15000000.333333',
            'status' => 'aktif',
        ]);
        $item3->refresh();
        
        $this->assertEquals('30000000.6666660000', $item3->total_price);

        // Skenario 4: Volume desimal dengan harga desimal. (Contoh: 1.5 × 3333.3333)
        $item4 = RabItem::create([
            'category_id' => $this->category->id,
            'description' => 'Test Presisi 4',
            'volume' => 1.5,
            'unit' => 'kg',
            'unit_price' => '3333.3333',
            'status' => 'aktif',
        ]);
        $item4->refresh();
        
        $this->assertEquals('4999.9999500000', $item4->total_price);
    }
}
