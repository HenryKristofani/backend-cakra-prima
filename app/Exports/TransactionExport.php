<?php

namespace App\Exports;

use App\Models\Transaction;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class TransactionExport implements WithEvents, WithTitle
{
    protected Collection $transactions;
    protected string $periodLabel;
    protected string $klasifikasi;

    public function __construct(Collection $transactions, string $periodLabel, string $klasifikasi = 'SEMUA')
    {
        $this->transactions = $transactions;
        $this->periodLabel = $periodLabel;
        $this->klasifikasi = strtoupper($klasifikasi);
    }

    public function title(): string
    {
        return 'Laporan Arus Kas';
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $totalRows = $this->transactions->count();
                $lastDataRow = 9 + $totalRows - 1; // data starts at row 9

                // ── Header Section ────────────────────────────────────────────
                $this->writeHeaders($sheet);

                // ── Table Headers ─────────────────────────────────────────────
                $this->writeTableHeaders($sheet);

                // ── Data Rows ─────────────────────────────────────────────────
                $this->writeDataRows($sheet, $lastDataRow);

                // ── Column Widths ─────────────────────────────────────────────
                $sheet->getColumnDimension('A')->setWidth(14);
                $sheet->getColumnDimension('B')->setWidth(40);
                $sheet->getColumnDimension('C')->setWidth(12);
                $sheet->getColumnDimension('D')->setWidth(18);
                $sheet->getColumnDimension('E')->setWidth(18);
            },
        ];
    }

    private function writeHeaders(Worksheet $sheet): void
    {
        // Row 1: LPSE
        $sheet->setCellValue('A1', 'LPSE');
        $sheet->mergeCells('A1:E1');
        $sheet->getStyle('A1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 12],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        // Row 2: LAPORAN ARUS KAS
        $sheet->setCellValue('A2', 'LAPORAN ARUS KAS');
        $sheet->mergeCells('A2:E2');
        $sheet->getStyle('A2')->applyFromArray([
            'font' => ['bold' => true, 'size' => 12],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        // Row 3: PERIODE
        $sheet->setCellValue('A3', 'PERIODE ' . $this->periodLabel);
        $sheet->mergeCells('A3:E3');
        $sheet->getStyle('A3')->applyFromArray([
            'font' => ['bold' => true, 'size' => 11],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        // Row 4: KLASIFIKASI
        $sheet->setCellValue('A4', 'KLASIFIKASI : ' . $this->klasifikasi);
        $sheet->mergeCells('A4:E4');
        $sheet->getStyle('A4')->applyFromArray([
            'font' => ['bold' => true, 'size' => 11],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        // Row 5, 6: blank spacer
        $sheet->getRowDimension(5)->setRowHeight(6);
        $sheet->getRowDimension(6)->setRowHeight(6);
    }

    private function writeTableHeaders(Worksheet $sheet): void
    {
        $headers = ['TANGGAL', 'KETERANGAN', 'CASH/REK', 'OUT', 'IN'];
        $cols = ['A', 'B', 'C', 'D', 'E'];
        $row = 7;

        foreach ($cols as $i => $col) {
            $sheet->setCellValue("{$col}{$row}", $headers[$i]);
        }

        $sheet->getStyle("A{$row}:E{$row}")->applyFromArray([
            'font' => ['bold' => true],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['argb' => 'FFBFBFBF'],
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['argb' => 'FF000000'],
                ],
            ],
        ]);

        $sheet->getRowDimension($row)->setRowHeight(22);
    }

    private function writeDataRows(Worksheet $sheet, int $lastDataRow): void
    {
        $row = 8;
        $currencyFormat = '"Rp "#,##0';

        // Ensure at least 10 visible rows for empty look
        $minRows = max($this->transactions->count(), 10);

        foreach ($this->transactions as $trx) {
            $tanggal = \Carbon\Carbon::parse($trx->date)->isoFormat('DD MMMM YYYY');
            $sheet->setCellValue("A{$row}", $tanggal);
            $sheet->setCellValue("B{$row}", strtoupper($trx->description . ($trx->company ? ' - ' . $trx->company : '')));
            $sheet->setCellValue("C{$row}", strtoupper($trx->payment_method));

            $expense = (float) $trx->expense;
            $income  = (float) $trx->income;

            if ($expense > 0) {
                $sheet->setCellValue("D{$row}", $expense);
                $sheet->getStyle("D{$row}")->getNumberFormat()->setFormatCode($currencyFormat);
            }
            if ($income > 0) {
                $sheet->setCellValue("E{$row}", $income);
                $sheet->getStyle("E{$row}")->getNumberFormat()->setFormatCode($currencyFormat);
                $sheet->getStyle("E{$row}")->getFont()->getColor()->setARGB('FFFF0000');
            }

            // Style data row
            $sheet->getStyle("A{$row}:E{$row}")->applyFromArray([
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                        'color' => ['argb' => 'FF000000'],
                    ],
                ],
                'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
            ]);

            $sheet->getStyle("A{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
            $sheet->getStyle("C{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("D{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            $sheet->getStyle("E{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            $sheet->getRowDimension($row)->setRowHeight(18);

            $row++;
        }

        // Fill empty rows to match minRows
        for ($r = $row; $r < 8 + $minRows; $r++) {
            $sheet->getStyle("A{$r}:E{$r}")->applyFromArray([
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                        'color' => ['argb' => 'FF000000'],
                    ],
                ],
            ]);
            $sheet->getRowDimension($r)->setRowHeight(18);
        }
    }
}
