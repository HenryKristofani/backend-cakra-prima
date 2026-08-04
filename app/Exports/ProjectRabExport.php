<?php

namespace App\Exports;

use App\Models\Project;
use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

class ProjectRabExport implements FromView, WithEvents, WithTitle
{
    protected array $data;
    protected Project $project;

    public function __construct(array $data, Project $project)
    {
        $this->data = $data;
        $this->project = $project;
    }

    public function view(): View
    {
        return view('exports.rab', [
            'data' => $this->data,
            'project' => $this->project,
        ]);
    }

    public function title(): string
    {
        return substr('RAB ' . $this->project->name, 0, 31); // Max title length in Excel is 31
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $highestRow = $sheet->getHighestRow();

                // Column Widths
                $sheet->getColumnDimension('A')->setWidth(10); // NO / CODE
                $sheet->getColumnDimension('B')->setWidth(50); // URAIAN PEKERJAAN
                $sheet->getColumnDimension('C')->setWidth(12); // VOL
                $sheet->getColumnDimension('D')->setWidth(12); // SAT
                $sheet->getColumnDimension('E')->setWidth(20); // HARGA SATUAN
                $sheet->getColumnDimension('F')->setWidth(20); // JUMLAH HARGA
                $sheet->getColumnDimension('G')->setWidth(15); // BOBOT (%)

                // Number Formatting (E and F are prices)
                $sheet->getStyle("E5:F{$highestRow}")->getNumberFormat()->setFormatCode('#,##0');

                // Alignments
                $sheet->getStyle("A5:A{$highestRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("B1:B{$highestRow}")->getAlignment()->setWrapText(true);
                $sheet->getStyle("C5:D{$highestRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("A1:G{$highestRow}")->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
            },
        ];
    }
}
