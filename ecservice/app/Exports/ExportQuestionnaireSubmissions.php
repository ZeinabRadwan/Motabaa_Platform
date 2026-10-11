<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;

class ExportQuestionnaireSubmissions extends BaseExport implements FromArray, WithHeadings, ShouldAutoSize, WithEvents, WithTitle
{
    use Exportable;

    /**
     * @param  array<int, string>  $headings
     * @param  array<int, array<int, string|null>>  $rows
     */
    public function __construct(
        protected array $headings,
        protected array $rows,
        protected string $sheetTitle,
    ) {
    }

    public function array(): array
    {
        return $this->rows;
    }

    public function headings(): array
    {
        return $this->headings;
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate()->setRightToLeft(true);
                $lastRow = max(1, $sheet->getHighestRow());
                $lastColumn = $sheet->getHighestColumn();

                $sheet->getStyle("A1:{$lastColumn}1")->getFont()->setBold(true);
                $sheet->getStyle("A1:{$lastColumn}1")->getFill()
                    ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                    ->getStartColor()->setARGB('31b261');

                $sheet->getStyle("A1:{$lastColumn}{$lastRow}")
                    ->getAlignment()
                    ->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT);

                $sheet->getStyle("A1:{$lastColumn}1")
                    ->getAlignment()
                    ->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);

                $sheet->freezePane('A2');
            },
        ];
    }

    public function title(): string
    {
        $title = trim($this->sheetTitle);

        return $title !== '' ? mb_substr($title, 0, 31) : 'Questionnaire';
    }
}
