<?php

namespace Tests\Unit;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\Project;
use App\Models\RabCategory;
use App\Models\RabItem;
use App\Models\ProgressReport;

class RabProgressCalculationTest extends TestCase
{
    use RefreshDatabase;

    public function test_weighted_progress_calculation()
    {
        // Seed project and RAB items using values extracted from the PDF example
        $project = Project::create(['name' => 'Rumah Ibu Evi', 'status' => 'aktif']);

        $data = [
            // A - PEKERJAAN BONGKARAN
            ['cat' => 'A', 'total' => 871200.00, 'prog' => 100],
            ['cat' => 'A', 'total' => 1050000.00, 'prog' => 100],
            ['cat' => 'A', 'total' => 528000.00, 'prog' => 100],
            ['cat' => 'A', 'total' => 316800.00, 'prog' => 100],
            ['cat' => 'A', 'total' => 135000.00, 'prog' => 100],
            ['cat' => 'A', 'total' => 226800.00, 'prog' => 100],
            ['cat' => 'A', 'total' => 627000.00, 'prog' => 100],
            ['cat' => 'A', 'total' => 500000.00, 'prog' => 100],
            // B - PEKERJAAN GALIAN
            ['cat' => 'B', 'total' => 500000.00, 'prog' => 100],
            ['cat' => 'B', 'total' => 39680.00, 'prog' => 100],
            ['cat' => 'B', 'total' => 742400.00, 'prog' => 100],
            // C - BETON BERTULANG
            ['cat' => 'C', 'total' => 612150.00, 'prog' => 100],
            ['cat' => 'C', 'total' => 285862.50, 'prog' => 100],
            ['cat' => 'C', 'total' => 268537.50, 'prog' => 100],
            ['cat' => 'C', 'total' => 389812.50, 'prog' => 100],
            ['cat' => 'C', 'total' => 924000.00, 'prog' => 100],
            ['cat' => 'C', 'total' => 773850.00, 'prog' => 50],
            // D - PASANGAN
            ['cat' => 'D', 'total' => 1303200.00, 'prog' => 100],
            ['cat' => 'D', 'total' => 2439600.00, 'prog' => 100],
            ['cat' => 'D', 'total' => 1440000.00, 'prog' => 100],
            ['cat' => 'D', 'total' => 2656200.00, 'prog' => 100],
            ['cat' => 'D', 'total' => 630000.00, 'prog' => 20],
            ['cat' => 'D', 'total' => 375000.00, 'prog' => 20],
            ['cat' => 'D', 'total' => 573300.00, 'prog' => 20],
            ['cat' => 'D', 'total' => 4569600.00, 'prog' => 20],
            ['cat' => 'D', 'total' => 5250000.00, 'prog' => 20],
            ['cat' => 'D', 'total' => 5250000.00, 'prog' => 20],
            ['cat' => 'D', 'total' => 2500000.00, 'prog' => 20],
            // E - PLAFOND (prog 0 for these items)
            ['cat' => 'E', 'total' => 1610000.00, 'prog' => 0],
            ['cat' => 'E', 'total' => 600000.00, 'prog' => 0],
            ['cat' => 'E', 'total' => 400000.00, 'prog' => 0],
            ['cat' => 'E', 'total' => 3910000.00, 'prog' => 0],
            ['cat' => 'E', 'total' => 875000.00, 'prog' => 0],
            ['cat' => 'E', 'total' => 800000.00, 'prog' => 0],
            ['cat' => 'E', 'total' => 1425000.00, 'prog' => 0],
            ['cat' => 'E', 'total' => 245000.00, 'prog' => 0],
            ['cat' => 'E', 'total' => 200000.00, 'prog' => 0],
            ['cat' => 'E', 'total' => 332500.00, 'prog' => 0],
            ['cat' => 'E', 'total' => 1950000.00, 'prog' => 0],
            ['cat' => 'E', 'total' => 651000.00, 'prog' => 0],
            ['cat' => 'E', 'total' => 200000.00, 'prog' => 0],
            ['cat' => 'E', 'total' => 455000.00, 'prog' => 0],
            // F - SANITASI
            ['cat' => 'F', 'total' => 1800000.00, 'prog' => 100],
            ['cat' => 'F', 'total' => 85000.00, 'prog' => 50],
            ['cat' => 'F', 'total' => 100000.00, 'prog' => 30],
            ['cat' => 'F', 'total' => 500000.00, 'prog' => 30],
            ['cat' => 'F', 'total' => 800000.00, 'prog' => 30],
            ['cat' => 'F', 'total' => 595000.00, 'prog' => 30],
            ['cat' => 'F', 'total' => 320000.00, 'prog' => 30],
            // G - PENGECATAN
            ['cat' => 'G', 'total' => 6660000.00, 'prog' => 40],
            // H - ATAP (included in A-I total; prog 0 here)
            ['cat' => 'H', 'total' => 14400000.00, 'prog' => 0],
            // I - LAIN-LAIN
            ['cat' => 'I', 'total' => 2800000.00, 'prog' => 70],
        ];

        // create categories map
        $categories = [];
        foreach ($data as $row) {
            $code = $row['cat'];
            if (! isset($categories[$code])) {
                $categories[$code] = RabCategory::create(['project_id' => $project->id, 'code' => $code, 'name' => 'Cat '.$code]);
            }

            $cat = $categories[$code];

            // create item: use volume=1 and unit_price = total so accessor matches PDF total
            $status = $code === 'H' ? 'dibatalkan' : 'aktif';
            $item = RabItem::create([
                'category_id' => $cat->id,
                'description' => 'Item '.$code,
                'volume' => 1,
                'unit' => 'ls',
                'unit_price' => $row['total'],
                'status' => $status,
            ]);

            // add latest progress if prog > 0
            if ($row['prog'] > 0) {
                ProgressReport::create([
                    'rab_item_id' => $item->id,
                    'report_date' => now()->toDateString(),
                    'percentage_complete' => $row['prog'],
                ]);
            }
        }

        // Assert overall progress equals the PDF reference (43.17%)
        $overall = $project->overall_progress_percentage;
        $this->assertEqualsWithDelta(43.17, $overall, 0.05);
    }
}
