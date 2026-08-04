<table>
    <thead>
        <tr>
            <th colspan="7" style="text-align: center; font-size: 14px; font-weight: bold;">
                RENCANA ANGGARAN BIAYA (RAB)
            </th>
        </tr>
        <tr>
            <th colspan="7" style="text-align: center; font-size: 12px; font-weight: bold;">
                PROYEK: {{ strtoupper($project->name) }}
            </th>
        </tr>
        <tr>
            <th colspan="7"></th>
        </tr>
        <tr>
            <th style="font-weight: bold; text-align: center; border: 1px solid #000000; background-color: #f3f4f6; height: 30px; vertical-align: middle;">NO</th>
            <th style="font-weight: bold; text-align: center; border: 1px solid #000000; background-color: #f3f4f6; vertical-align: middle;">URAIAN PEKERJAAN</th>
            <th style="font-weight: bold; text-align: center; border: 1px solid #000000; background-color: #f3f4f6; vertical-align: middle;">VOL</th>
            <th style="font-weight: bold; text-align: center; border: 1px solid #000000; background-color: #f3f4f6; vertical-align: middle;">SAT</th>
            <th style="font-weight: bold; text-align: center; border: 1px solid #000000; background-color: #f3f4f6; vertical-align: middle;">HARGA SATUAN</th>
            <th style="font-weight: bold; text-align: center; border: 1px solid #000000; background-color: #f3f4f6; vertical-align: middle;">JUMLAH HARGA</th>
            <th style="font-weight: bold; text-align: center; border: 1px solid #000000; background-color: #f3f4f6; vertical-align: middle;">BOBOT (%)</th>
        </tr>
    </thead>
    <tbody>
        @php
            $renderCategory = function($category, $depth) use (&$renderCategory) {
                // Category Header Row
                echo '<tr>';
                echo '<td style="border: 1px solid #000000; font-weight: bold; text-align: center;">' . htmlspecialchars($category['code'] ?? '') . '</td>';
                echo '<td style="border: 1px solid #000000; font-weight: bold; background-color: ' . ($depth === 1 ? '#e5e7eb' : '#f9fafb') . ';">' . ($depth === 1 ? strtoupper(htmlspecialchars($category['name'])) : htmlspecialchars($category['name'])) . '</td>';
                echo '<td style="border: 1px solid #000000; background-color: ' . ($depth === 1 ? '#e5e7eb' : '#f9fafb') . ';"></td>';
                echo '<td style="border: 1px solid #000000; background-color: ' . ($depth === 1 ? '#e5e7eb' : '#f9fafb') . ';"></td>';
                echo '<td style="border: 1px solid #000000; background-color: ' . ($depth === 1 ? '#e5e7eb' : '#f9fafb') . ';"></td>';
                echo '<td style="border: 1px solid #000000; background-color: ' . ($depth === 1 ? '#e5e7eb' : '#f9fafb') . ';"></td>';
                echo '<td style="border: 1px solid #000000; text-align: center; background-color: ' . ($depth === 1 ? '#e5e7eb' : '#f9fafb') . ';"></td>';
                echo '</tr>';

                // Items
                $idx = 1;
                foreach ($category['items'] as $item) {
                    echo '<tr>';
                    echo '<td style="border: 1px solid #000000; text-align: center;">' . $idx++ . '</td>';
                    echo '<td style="border: 1px solid #000000;">' . htmlspecialchars($item['description']);
                    if ($item['status'] === 'dikurangi') {
                        echo ' <span style="color: #ef4444; font-weight: bold;">(Dikurangi)</span>';
                    }
                    echo '</td>';
                    echo '<td style="border: 1px solid #000000; text-align: center;">' . htmlspecialchars($item['volume']) . '</td>';
                    echo '<td style="border: 1px solid #000000; text-align: center;">' . htmlspecialchars($item['unit']) . '</td>';
                    echo '<td style="border: 1px solid #000000; text-align: right;">' . (float)$item['unit_price'] . '</td>';
                    
                    if ($item['status'] === 'dikurangi') {
                        echo '<td style="border: 1px solid #000000; text-align: right; color: #ef4444; text-decoration: line-through;">' . (float)$item['total_price'] . '</td>';
                    } else {
                        echo '<td style="border: 1px solid #000000; text-align: right;">' . (float)$item['total_price'] . '</td>';
                    }

                    echo '<td style="border: 1px solid #000000; text-align: center;">' . number_format($item['bobot_percentage'], 2) . '%</td>';
                    echo '</tr>';
                }

                // Subcategories
                foreach ($category['children'] as $child) {
                    $renderCategory($child, $depth + 1);
                }

                // Subtotal for Depth 1
                if ($depth === 1) {
                    echo '<tr>';
                    echo '<td colspan="5" style="border: 1px solid #000000; font-weight: bold; text-align: right; background-color: #f3f4f6;">Jumlah ' . htmlspecialchars($category['code'] ?? $category['name']) . '</td>';
                    
                    $sumPrices = function($cat) use (&$sumPrices) {
                        $total = 0;
                        foreach($cat['items'] as $it) {
                            if ($it['status'] !== 'dikurangi') {
                                $total += (float)$it['total_price'];
                            }
                        }
                        foreach($cat['children'] as $ch) {
                            $total += $sumPrices($ch);
                        }
                        return $total;
                    };
                    $catTotal = $sumPrices($category);
                    
                    echo '<td style="border: 1px solid #000000; font-weight: bold; background-color: #fef08a; text-align: right;">' . $catTotal . '</td>';
                    echo '<td style="border: 1px solid #000000; font-weight: bold; text-align: center; background-color: #f3f4f6;">' . number_format($category['total_bobot_percentage'], 2) . '%</td>';
                    echo '</tr>';
                }
            };
        @endphp

        @foreach($data['categories'] as $category)
            @php $renderCategory($category, 1); @endphp
        @endforeach

        <!-- GRAND TOTALS -->
        <tr>
            <th colspan="7"></th>
        </tr>
        <tr>
            <td colspan="5" style="border: 1px solid #000000; font-weight: bold; text-align: right; background-color: #e5e7eb;">TOTAL RAB AKTIF</td>
            <td style="border: 1px solid #000000; font-weight: bold; text-align: right; background-color: #e5e7eb;">{{ $data['total_rab_aktif'] }}</td>
            <td style="border: 1px solid #000000; font-weight: bold; text-align: center; background-color: #e5e7eb;">100.00%</td>
        </tr>

        @if(count($data['deductions']) > 0)
        <tr>
            <th colspan="7"></th>
        </tr>
        <tr>
            <th colspan="7" style="font-weight: bold; text-align: left; background-color: #fecdd3; border: 1px solid #000000;">PENGURANGAN (DEDUCTION)</th>
        </tr>
        @foreach($data['deductions'] as $idx => $deduction)
        <tr>
            <td style="border: 1px solid #000000; text-align: center; color: #b91c1c;">{{ $idx + 1 }}</td>
            <td style="border: 1px solid #000000; color: #b91c1c;">{{ $deduction['description'] }}</td>
            <td style="border: 1px solid #000000; text-align: center; color: #b91c1c;">{{ $deduction['volume'] }}</td>
            <td style="border: 1px solid #000000; text-align: center; color: #b91c1c;">{{ $deduction['unit'] }}</td>
            <td style="border: 1px solid #000000; text-align: right; color: #b91c1c;">{{ $deduction['unit_price'] }}</td>
            <td style="border: 1px solid #000000; text-align: right; color: #b91c1c;">{{ $deduction['total_price'] }}</td>
            <td style="border: 1px solid #000000; text-align: center; color: #b91c1c;">0.00%</td>
        </tr>
        @endforeach
        <tr>
            <td colspan="5" style="border: 1px solid #000000; font-weight: bold; text-align: right; background-color: #ffe4e6; color: #b91c1c;">TOTAL PENGURANGAN</td>
            <td style="border: 1px solid #000000; font-weight: bold; text-align: right; background-color: #ffe4e6; color: #b91c1c;">{{ $data['total_deduction'] }}</td>
            <td style="border: 1px solid #000000; background-color: #ffe4e6;"></td>
        </tr>
        <tr>
            <td colspan="5" style="border: 1px solid #000000; font-weight: bold; text-align: right; background-color: #d1fae5;">TOTAL KONTRAK (SETELAH PENGURANGAN)</td>
            <td style="border: 1px solid #000000; font-weight: bold; text-align: right; background-color: #d1fae5;">{{ $data['final_total'] }}</td>
            <td style="border: 1px solid #000000; background-color: #d1fae5;"></td>
        </tr>
        <tr>
            <td colspan="5" style="border: 1px solid #000000; font-weight: bold; text-align: right; background-color: #a7f3d0;">PEMBULATAN</td>
            <td style="border: 1px solid #000000; font-weight: bold; text-align: right; background-color: #a7f3d0;">{{ $data['rounded_total'] }}</td>
            <td style="border: 1px solid #000000; background-color: #a7f3d0;"></td>
        </tr>
        @else
        <tr>
            <td colspan="5" style="border: 1px solid #000000; font-weight: bold; text-align: right; background-color: #a7f3d0;">PEMBULATAN</td>
            <td style="border: 1px solid #000000; font-weight: bold; text-align: right; background-color: #a7f3d0;">{{ $data['rounded_total'] }}</td>
            <td style="border: 1px solid #000000; background-color: #a7f3d0;"></td>
        </tr>
        @endif
    </tbody>
</table>
