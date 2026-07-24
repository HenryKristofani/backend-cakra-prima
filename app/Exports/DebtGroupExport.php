<?php

namespace App\Exports;

use App\Models\DebtGroup;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class DebtGroupExport implements WithEvents, WithTitle
{
    protected DebtGroup $debtGroup;

    public function __construct(DebtGroup $debtGroup)
    {
        $this->debtGroup = $debtGroup;
    }

    public function title(): string
    {
        return substr($this->debtGroup->name, 0, 31); // Sheet name max 31 chars
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $this->buildSheet($sheet);
            },
        ];
    }

    private function buildSheet(Worksheet $sheet): void
    {
        $group    = $this->debtGroup;
        $items    = $group->items ?? collect();
        $payments = $group->payments ?? collect();

        $currencyFormat = '"Rp "#,##0';

        // ── Column widths ────────────────────────────────────────────────
        $sheet->getColumnDimension('A')->setWidth(8);
        $sheet->getColumnDimension('B')->setWidth(48);
        $sheet->getColumnDimension('C')->setWidth(18);
        $sheet->getColumnDimension('D')->setWidth(22);

        // ── Row 1: Title ─────────────────────────────────────────────────
        $sheet->setCellValue('A1', strtoupper($group->name));
        $sheet->mergeCells('A1:D1');
        $sheet->getStyle('A1')->applyFromArray([
            'font'      => ['bold' => true, 'size' => 13],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(22);

        // ── Row 2: blank spacer ───────────────────────────────────────────
        $sheet->getRowDimension(2)->setRowHeight(6);

        // ── Row 3: Table Headers ──────────────────────────────────────────
        $headerRow = 3;
        $sheet->setCellValue("A{$headerRow}", 'NO');
        $sheet->setCellValue("B{$headerRow}", strtoupper($group->name));
        $sheet->setCellValue("C{$headerRow}", 'TANGGAL');
        $sheet->setCellValue("D{$headerRow}", 'NOMINAL');

        $sheet->getStyle("A{$headerRow}:D{$headerRow}")->applyFromArray([
            'font'      => ['bold' => true],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical'   => Alignment::VERTICAL_CENTER,
            ],
            'fill' => [
                'fillType'   => Fill::FILL_SOLID,
                'startColor' => ['argb' => 'FFFFC000'], // amber/yellow like the image
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color'       => ['argb' => 'FF000000'],
                ],
            ],
        ]);
        $sheet->getStyle("B{$headerRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        $sheet->getRowDimension($headerRow)->setRowHeight(22);

        // ── Rows 4+: Items ────────────────────────────────────────────────
        $row = 4;
        foreach ($items as $item) {
            $no     = $item->no ?? '';
            $desc   = strtoupper($item->description);
            $date   = $item->trans_date
                ? \Carbon\Carbon::parse($item->trans_date)->format('d-M-y')
                : '';
            $amount = (float) $item->amount;

            $sheet->setCellValue("A{$row}", $no);
            $sheet->setCellValue("B{$row}", $desc);
            $sheet->setCellValue("C{$row}", $date);
            $sheet->setCellValue("D{$row}", $amount);
            $sheet->getStyle("D{$row}")->getNumberFormat()->setFormatCode($currencyFormat);

            $sheet->getStyle("A{$row}:D{$row}")->applyFromArray([
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                        'color'       => ['argb' => 'FF000000'],
                    ],
                ],
                'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
            ]);
            $sheet->getStyle("A{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("C{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("D{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            $sheet->getRowDimension($row)->setRowHeight(18);

            $row++;
        }

        // ── Blank spacer row ──────────────────────────────────────────────
        $sheet->getRowDimension($row)->setRowHeight(6);
        $row++;

        // ── TOTAL HUTANG row ──────────────────────────────────────────────
        $totalRow = $row;
        $sheet->setCellValue("A{$totalRow}", 'TOTAL HUTANG');
        $sheet->mergeCells("A{$totalRow}:C{$totalRow}");
        $sheet->setCellValue("D{$totalRow}", (float) $group->total_amount);
        $sheet->getStyle("D{$totalRow}")->getNumberFormat()->setFormatCode($currencyFormat);

        $sheet->getStyle("A{$totalRow}:D{$totalRow}")->applyFromArray([
            'font'      => ['bold' => true],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical'   => Alignment::VERTICAL_CENTER,
            ],
            'fill' => [
                'fillType'   => Fill::FILL_SOLID,
                'startColor' => ['argb' => 'FFFFFF00'], // bright yellow
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color'       => ['argb' => 'FF000000'],
                ],
            ],
        ]);
        $sheet->getStyle("D{$totalRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $sheet->getRowDimension($totalRow)->setRowHeight(20);
        $row++;

        // ── Payments ──────────────────────────────────────────────────────
        foreach ($payments as $payment) {
            $desc   = strtoupper($payment->description);
            $date   = $payment->payment_date
                ? \Carbon\Carbon::parse($payment->payment_date)->format('d-M-y')
                : '';
            $amount = (float) $payment->amount;

            $sheet->setCellValue("A{$row}", $desc);
            $sheet->mergeCells("A{$row}:B{$row}");
            $sheet->setCellValue("C{$row}", $date);
            $sheet->setCellValue("D{$row}", $amount);
            $sheet->getStyle("D{$row}")->getNumberFormat()->setFormatCode($currencyFormat);

            $sheet->getStyle("A{$row}:D{$row}")->applyFromArray([
                'font'      => ['bold' => true],
                'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
                'fill' => [
                    'fillType'   => Fill::FILL_SOLID,
                    'startColor' => ['argb' => 'FF92D050'], // green like image
                ],
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                        'color'       => ['argb' => 'FF000000'],
                    ],
                ],
            ]);
            $sheet->getStyle("A{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
            $sheet->getStyle("C{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("D{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            $sheet->getRowDimension($row)->setRowHeight(20);

            $row++;
        }

        // ── SISA HUTANG row ───────────────────────────────────────────────
        $sisaRow = $row;
        $sheet->setCellValue("A{$sisaRow}", 'SISA HUTANG');
        $sheet->mergeCells("A{$sisaRow}:C{$sisaRow}");
        $sheet->setCellValue("D{$sisaRow}", (float) $group->remaining_amount);
        $sheet->getStyle("D{$sisaRow}")->getNumberFormat()->setFormatCode($currencyFormat);

        $sheet->getStyle("A{$sisaRow}:D{$sisaRow}")->applyFromArray([
            'font'      => ['bold' => true, 'size' => 12],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical'   => Alignment::VERTICAL_CENTER,
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_MEDIUM,
                    'color'       => ['argb' => 'FF000000'],
                ],
            ],
        ]);
        $sheet->getStyle("D{$sisaRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $sheet->getRowDimension($sisaRow)->setRowHeight(22);
    }
}
