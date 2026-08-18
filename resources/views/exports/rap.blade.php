<table>
    <thead>
        <tr>
            <th colspan="8" style="text-align: center; font-size: 14px; font-weight: bold;">
                RENCANA ANGGARAN PELAKSANAAN (RAP)
            </th>
        </tr>
        <tr>
            <th colspan="8"></th>
        </tr>
        <tr>
            <th colspan="2" style="text-align: left; font-weight: bold;">KEGIATAN</th>
            <th colspan="4" style="text-align: left;">: {{ $project->kegiatan ?? '' }}</th>
            <th colspan="2" style="text-align: right;">Pajak & Biaya Admin: {{ number_format(\App\Models\RapSetting::resolvePajak($project->id), 2) }}%</th>
        </tr>
        <tr>
            <th colspan="2" style="text-align: left; font-weight: bold;">PEKERJAAN</th>
            <th colspan="6" style="text-align: left;">: {{ $project->name }}</th>
        </tr>
        <tr>
            <th colspan="2" style="text-align: left; font-weight: bold;">LOKASI</th>
            <th colspan="6" style="text-align: left;">: {{ $project->location ?? '' }}</th>
        </tr>
        <tr>
            <th colspan="2" style="text-align: left; font-weight: bold;">TAHUN</th>
            <th colspan="6" style="text-align: left;">: {{ $project->rab_date ? \Carbon\Carbon::parse($project->rab_date)->format('Y') : '' }}</th>
        </tr>
        <tr>
            <th colspan="8"></th>
        </tr>
        <tr>
            <th rowspan="2" style="font-weight: bold; text-align: center; border: 1px solid #000000; background-color: #f3f4f6; vertical-align: middle;">NO</th>
            <th rowspan="2" style="font-weight: bold; text-align: center; border: 1px solid #000000; background-color: #f3f4f6; vertical-align: middle;">URAIAN PEKERJAAN</th>
            <th rowspan="2" style="font-weight: bold; text-align: center; border: 1px solid #000000; background-color: #f3f4f6; vertical-align: middle;">VOLUME</th>
            <th rowspan="2" style="font-weight: bold; text-align: center; border: 1px solid #000000; background-color: #f3f4f6; vertical-align: middle;">SATUAN</th>
            <th colspan="2" style="font-weight: bold; text-align: center; border: 1px solid #000000; background-color: #f3f4f6; vertical-align: middle;">HARGA KONTRAK</th>
            <th colspan="2" style="font-weight: bold; text-align: center; border: 1px solid #000000; background-color: #f3f4f6; vertical-align: middle;">HARGA RAP</th>
        </tr>
        <tr>
            <th style="font-weight: bold; text-align: center; border: 1px solid #000000; background-color: #f3f4f6; vertical-align: middle;">Harga Satuan</th>
            <th style="font-weight: bold; text-align: center; border: 1px solid #000000; background-color: #f3f4f6; vertical-align: middle;">Jumlah Harga</th>
            <th style="font-weight: bold; text-align: center; border: 1px solid #000000; background-color: #f3f4f6; vertical-align: middle;">Harga Satuan</th>
            <th style="font-weight: bold; text-align: center; border: 1px solid #000000; background-color: #f3f4f6; vertical-align: middle;">Jumlah Harga</th>
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
                echo '<td style="border: 1px solid #000000; background-color: ' . ($depth === 1 ? '#e5e7eb' : '#f9fafb') . ';"></td>';
                echo '<td style="border: 1px solid #000000; background-color: ' . ($depth === 1 ? '#e5e7eb' : '#f9fafb') . ';"></td>';
                echo '</tr>';

                // Items
                $idx = 1;
                foreach ($category['items'] as $item) {
                    echo '<tr>';
                    echo '<td style="border: 1px solid #000000; text-align: center;">' . $idx++ . '</td>';
                    echo '<td style="border: 1px solid #000000;">' . htmlspecialchars($item['description']) . '</td>';
                    echo '<td style="border: 1px solid #000000; text-align: center;">' . htmlspecialchars($item['volume']) . '</td>';
                    echo '<td style="border: 1px solid #000000; text-align: center;">' . htmlspecialchars($item['unit']) . '</td>';
                    
                    // Harga Kontrak
                    if (!empty($item['source_rab_item'])) {
                        $rabPrice = (float) $item['source_rab_item']['unit_price'];
                        $rabTotal = (float) $item['volume'] * $rabPrice;
                        echo '<td style="border: 1px solid #000000; text-align: right;">' . $rabPrice . '</td>';
                        echo '<td style="border: 1px solid #000000; text-align: right;">' . $rabTotal . '</td>';
                    } else {
                        echo '<td style="border: 1px solid #000000; text-align: center;">-</td>';
                        echo '<td style="border: 1px solid #000000; text-align: center;">-</td>';
                    }

                    // Harga RAP
                    $rapPrice = (float) $item['effective_unit_price'];
                    $rapTotal = (float) $item['total_price'];
                    echo '<td style="border: 1px solid #000000; text-align: right;">' . $rapPrice . '</td>';
                    echo '<td style="border: 1px solid #000000; text-align: right;">' . $rapTotal . '</td>';
                    echo '</tr>';
                }

                // Subcategories
                foreach ($category['children'] as $child) {
                    $renderCategory($child, $depth + 1);
                }

                // Subtotal
                echo '<tr>';
                echo '<td colspan="4" style="border: 1px solid #000000; font-weight: bold; text-align: right; background-color: #f3f4f6;">Jumlah ' . htmlspecialchars($category['code'] ?? $category['name']) . '</td>';
                
                $sumPrices = function($cat) use (&$sumPrices) {
                    $totalRab = 0;
                    $totalRap = 0;
                    foreach($cat['items'] as $it) {
                        if (!empty($it['source_rab_item'])) {
                            $totalRab += (float) $it['volume'] * (float) $it['source_rab_item']['unit_price'];
                        }
                        $totalRap += (float) $it['total_price'];
                    }
                    foreach($cat['children'] as $ch) {
                        $totals = $sumPrices($ch);
                        $totalRab += $totals['rab'];
                        $totalRap += $totals['rap'];
                    }
                    return ['rab' => $totalRab, 'rap' => $totalRap];
                };
                
                $totals = $sumPrices($category);
                
                echo '<td style="border: 1px solid #000000; font-weight: bold; text-align: right; background-color: #fef08a;"></td>'; // Harga Satuan Kontrak column is empty for total
                echo '<td style="border: 1px solid #000000; font-weight: bold; text-align: right; background-color: #fef08a;">' . $totals['rab'] . '</td>';
                echo '<td style="border: 1px solid #000000; font-weight: bold; text-align: right; background-color: #fef08a;"></td>'; // Harga Satuan RAP column is empty for total
                echo '<td style="border: 1px solid #000000; font-weight: bold; text-align: right; background-color: #fef08a;">' . $totals['rap'] . '</td>';
                echo '</tr>';
            };
        @endphp

        @php
            $grandTotalRab = 0;
            $grandTotalRap = 0;
            $calcTotal = function($cat) use (&$calcTotal) {
                $totalRab = 0;
                $totalRap = 0;
                foreach($cat['items'] as $it) {
                    if (!empty($it['source_rab_item'])) {
                        $totalRab += (float) $it['volume'] * (float) $it['source_rab_item']['unit_price'];
                    }
                    $totalRap += (float) $it['total_price'];
                }
                foreach($cat['children'] as $ch) {
                    $totals = $calcTotal($ch);
                    $totalRab += $totals['rab'];
                    $totalRap += $totals['rap'];
                }
                return ['rab' => $totalRab, 'rap' => $totalRap];
            };
        @endphp

        @foreach($data['categories'] as $category)
            @php 
                $renderCategory($category, 1); 
                $t = $calcTotal($category);
                $grandTotalRab += $t['rab'];
                $grandTotalRap += $t['rap'];
            @endphp
        @endforeach

        <!-- GRAND TOTALS -->
        <tr>
            <th colspan="8"></th>
        </tr>
        <tr>
            <td colspan="4" style="border: 1px solid #000000; font-weight: bold; text-align: right; background-color: #e5e7eb;">JUMLAH TOTAL</td>
            <td style="border: 1px solid #000000; font-weight: bold; text-align: right; background-color: #e5e7eb;"></td>
            <td style="border: 1px solid #000000; font-weight: bold; text-align: right; background-color: #e5e7eb;">{{ $grandTotalRab }}</td>
            <td style="border: 1px solid #000000; font-weight: bold; text-align: right; background-color: #e5e7eb;"></td>
            <td style="border: 1px solid #000000; font-weight: bold; text-align: right; background-color: #e5e7eb;">{{ $grandTotalRap }}</td>
        </tr>
    </tbody>
</table>
