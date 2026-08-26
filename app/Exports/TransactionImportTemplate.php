<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;

/**
 * Export a blank template for the transaction import feature.
 * The column order & names MUST match TransactionImport heading row parsing.
 */
class TransactionImportTemplate implements FromArray, WithHeadings, WithStyles
{
    public function array(): array
    {
        // Two example rows to guide the user
        return [
            ['2025-01-15', '', '', '', 'Contoh pembayaran material', 'PT Supplier X', 'cash', '', '5000000'],
            ['2025-01-16', 'Kas BNI', 'Nama Project', 'Bongkar Jalan', 'Terima pembayaran klien', '', 'rek', '10000000', ''],
        ];
    }

    public function headings(): array
    {
        return [
            'Tanggal',
            'Akun',
            'Project',
            'Item_RAP',
            'Deskripsi',
            'Perusahaan/Pihak',
            'Metode',
            'Pemasukan',
            'Pengeluaran',
        ];
    }

    public function styles(Worksheet $sheet)
    {
        // Bold + highlight header row
        $sheet->getStyle('A1:I1')->applyFromArray([
            'font' => ['bold' => true],
            'fill' => [
                'fillType'   => Fill::FILL_SOLID,
                'startColor' => ['argb' => 'FFBFBFBF'],
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color'       => ['argb' => 'FF000000'],
                ],
            ],
        ]);

        // Auto-width for all columns
        foreach (range('A', 'I') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        return [];
    }
}
