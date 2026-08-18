<?php

namespace App\Exports;

use App\Models\Project;
use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class ProjectRapExport implements FromView, WithEvents, WithTitle
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
        return view('exports.rap', [
            'data' => $this->data,
            'project' => $this->project,
        ]);
    }

    public function title(): string
    {
        return substr('RAP ' . $this->project->name, 0, 31); // Max title length in Excel is 31
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
                $sheet->getColumnDimension('E')->setWidth(20); // Harga Satuan (Kontrak)
                $sheet->getColumnDimension('F')->setWidth(20); // Jumlah Harga (Kontrak)
                $sheet->getColumnDimension('G')->setWidth(20); // Harga Satuan (RAP)
                $sheet->getColumnDimension('H')->setWidth(20); // Jumlah Harga (RAP)

                // Number Formatting (E, F, G, H are prices)
                $sheet->getStyle("E7:H{$highestRow}")->getNumberFormat()->setFormatCode('#,##0');

                // Alignments
                $sheet->getStyle("A7:A{$highestRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("B1:B{$highestRow}")->getAlignment()->setWrapText(true);
                $sheet->getStyle("C7:D{$highestRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("A1:H{$highestRow}")->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
            },
        ];
    }
}
