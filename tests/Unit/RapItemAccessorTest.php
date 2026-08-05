<?php

namespace Tests\Unit;

use App\Models\Project;
use App\Models\RapCategory;
use App\Models\RapItem;
use App\Models\RapSetting;
use App\Models\Transaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RapItemAccessorTest extends TestCase
{
    use RefreshDatabase;

    private function createProjectWithItem(
        float $unitPrice,
        float $volume,
        ?float $pajakPct,
        bool $isGlobal = false,
    ): RapItem {
        $project = Project::create([
            'name'   => 'Test Project',
            'status' => 'active',
        ]);

        if ($pajakPct !== null) {
            RapSetting::create([
                'project_id'          => $isGlobal ? null : $project->id,
                'pajak_percentage' => $pajakPct,
            ]);
        }

        $category = RapCategory::create([
            'project_id' => $project->id,
            'name'       => 'Kategori Test',
            'sort_order' => 0,
        ]);

        return RapItem::create([
            'category_id' => $category->id,
            'description' => 'Item Test',
            'volume'      => $volume,
            'unit'        => 'm2',
            'unit_price'  => $unitPrice,
            'sort_order'  => 0,
        ]);
    }

    public function test_effective_unit_price_with_project_specific_pajak(): void
    {
        // unit_price=100000, pajak=10%, expected effective=110000
        $item = $this->createProjectWithItem(
            unitPrice:   100_000,
            volume:      5,
            pajakPct: 10.0,
        );

        $item->load('category');
        $this->assertEquals(110_000.0, $item->effective_unit_price, 'effective_unit_price harus 110000');
    }

    public function test_total_price_is_volume_times_effective_unit_price(): void
    {
        // volume=5, effective=110000, expected total=550000
        $item = $this->createProjectWithItem(
            unitPrice:   100_000,
            volume:      5,
            pajakPct: 10.0,
        );

        $item->load('category');
        $this->assertEquals(550_000.0, $item->total_price, 'total_price harus 550000');
    }

    public function test_selisih_laba_rugi_positif_when_under_budget(): void
    {
        // total_price=550000, realisasi=400000, expected selisih=150000 (untung)
        $item = $this->createProjectWithItem(
            unitPrice:   100_000,
            volume:      5,
            pajakPct: 10.0,
        );

        \Illuminate\Support\Facades\DB::table('transactions')->insert([
            'rap_item_id'    => $item->id,
            'date'           => now()->toDateString(),
            'company'        => 'Test Vendor',
            'description'    => 'Realisasi biaya test',
            'payment_method' => 'cash',
            'expense'        => 400_000,
            'income'         => 0,
            'created_at'     => now(),
            'updated_at'     => now(),
        ]);

        $item->load('category');
        $this->assertEquals(400_000.0, $item->total_realisasi, 'total_realisasi harus 400000');
        $this->assertEquals(150_000.0,  $item->selisih_laba_rugi, 'selisih harus 150000 (untung)');
    }

    public function test_selisih_laba_rugi_negatif_when_over_budget(): void
    {
        $item = $this->createProjectWithItem(
            unitPrice:   100_000,
            volume:      5,
            pajakPct: 10.0,
        );

        \Illuminate\Support\Facades\DB::table('transactions')->insert([
            'rap_item_id'    => $item->id,
            'date'           => now()->toDateString(),
            'company'        => 'Test Vendor',
            'description'    => 'Realisasi over budget',
            'payment_method' => 'cash',
            'expense'        => 650_000,
            'income'         => 0,
            'created_at'     => now(),
            'updated_at'     => now(),
        ]);

        $item->load('category');
        $this->assertEquals(-100_000.0, $item->selisih_laba_rugi, 'selisih harus -100000 (rugi)');
    }

    public function test_resolves_global_pajak_when_no_project_specific_setting(): void
    {
        // Global 15%, no project-specific → harus pakai 15%
        RapSetting::create([
            'project_id'          => null,
            'pajak_percentage' => 15.0,
        ]);

        $project  = Project::create(['name' => 'Project Tanpa Setting', 'status' => 'active']);
        $category = RapCategory::create(['project_id' => $project->id, 'name' => 'Cat', 'sort_order' => 0]);
        $item     = RapItem::create([
            'category_id' => $category->id,
            'description' => 'Item fallback',
            'volume'      => 2,
            'unit'        => 'm',
            'unit_price'  => 100_000,
            'sort_order'  => 0,
        ]);

        $item->load('category');
        $this->assertEquals(115_000.0, $item->effective_unit_price, 'Harus fallback ke global 15%');
    }

    public function test_uses_zero_pajak_when_no_setting_exists(): void
    {
        $project  = Project::create(['name' => 'Project Zero', 'status' => 'active']);
        $category = RapCategory::create(['project_id' => $project->id, 'name' => 'Cat', 'sort_order' => 0]);
        $item     = RapItem::create([
            'category_id' => $category->id,
            'description' => 'Item zero pajak',
            'volume'      => 1,
            'unit'        => 'ls',
            'unit_price'  => 200_000,
            'sort_order'  => 0,
        ]);

        $item->load('category');
        $this->assertEquals(200_000.0, $item->effective_unit_price, 'Tanpa setting, effective = unit_price penuh');
        $this->assertEquals(200_000.0, $item->total_price);
    }

    public function test_project_specific_pajak_takes_priority_over_global(): void
    {
        // Global 20%, project-specific 5% → harus pakai 5%
        RapSetting::create(['project_id' => null, 'pajak_percentage' => 20.0]);

        $item = $this->createProjectWithItem(
            unitPrice:   100_000,
            volume:      1,
            pajakPct: 5.0,
            isGlobal:    false,
        );

        $item->load('category');
        $this->assertEquals(105_000.0, $item->effective_unit_price, 'Project-specific 5% harus menang atas global 20%');
    }
}
