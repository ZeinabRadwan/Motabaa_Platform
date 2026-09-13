<?php

namespace App\Exports;

use Maatwebsite\Excel\Events\AfterSheet;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\FromCollection;

class ExportAssessmentsSheet extends BaseExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize, WithEvents, WithTitle
{
    use Exportable;

    protected $data;
    protected $title;

    public function __construct($data, $title) {

        $this->data = $data;
        $this->title = $title;
    }

    public function collection() {
                    
        return collect($this->data);
    }

    public function headings(): array {

        $map = [];
        $headers = [];

        if(isset($this->data[0]))
            $map = $this->mapping($this->data[0]);

        foreach ($map as $key => $value) {
            
            $headers[] = $key;
        }

        return $headers;
    }

    public function map($item): array {

        return $this->mapping($item);
    }

    public function mapping($item) : array {

        $data = [];
        foreach ($item as $key => $value) {
            if($key == 0)
                $data['المجال'] = $value;
            else if($key == (count($item)-1))
                $data['الهدف السلوكي'] = $value;
            else
                $data[''] = $value;
        }

        return $data;
    }

    public function registerEvents() : array {

        return [
            AfterSheet::class => function(AfterSheet $event) {

                $nRows = count($this->data);

                $sheet = $event->sheet->getDelegate();
                $sheet = $event->sheet->getDelegate()->setRightToLeft(true);

                $lastRow = $sheet->getHighestRow();
                $lastColumn = $sheet->getHighestColumn();

                $sheet->getStyle("A1:{$lastColumn}1")->getFont()->setBold(true);
                $sheet->getStyle("A1:{$lastColumn}1")->getFill()
                ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                ->getStartColor()->setARGB('31b261');

                $sheet->getStyle("A2:{$lastColumn}{$lastRow}")->applyFromArray([
                    'alignment' => array(
                        'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT,
                    ),
                ]);

                $sheet->getStyle("A1:{$lastColumn}1")
                ->getAlignment()
                ->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);

                $sheet->freezePane('A2'); // Freeze First Row
            },
        ];
    }

    public function title(): string {

        return "{$this->title}";
    }
}