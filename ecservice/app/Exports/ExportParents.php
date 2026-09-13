<?php

namespace App\Exports;

use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\FromCollection;

class ExportParents extends BaseExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize, WithEvents, WithTitle
{
    use Exportable;

    protected $data;

    public function __construct($data) {

        $this->data = $data;
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

        $nationality = $item->nationality;
        if($nationality && !Str::contains(__("nationalities.{$nationality}"), 'nationalities.'))
            $nationality = __("nationalities.{$nationality}");

        return [
            'code' => $item->id,
            'name' => $item->name,
            'email' => $item->email,
            'nationality' => "{$nationality}",
            'id_or_residence_number' => $item->id_or_residence_number,
            'phone' => $item->phone,
            'address_unit' => $item->address_unit,
            'address_building' => $item->address_building,
            'address_street' => $item->address_street,
            'address_area' => $item->address_area,
            'address_city' => $item->address_city,
            'address_zipcode' => $item->address_zipcode,
            'address_number' => $item->address_number,
            
        ];
    }

    public function registerEvents() : array {

        return [
            AfterSheet::class => function(AfterSheet $event) {

                $sheet = $event->sheet->getDelegate();
                $sheet = $event->sheet->getDelegate()->setRightToLeft(true);

                $lastRow = $sheet->getHighestRow();
                $lastColumn = $sheet->getHighestColumn();

                $sheet->getStyle("A1:{$lastColumn}1")->getFont()->setBold(true);
                $sheet->getStyle("A1:{$lastColumn}1")->getFill()
                ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                ->getStartColor()->setARGB('31b261');

                $sheet->getStyle("A2:A{$lastRow}")->applyFromArray([
                    'alignment' => array(
                        'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT,
                    )
                ]);

                $sheet->getStyle("A1:{$lastColumn}1")
                ->getAlignment()
                ->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);

                $sheet->getStyle("E1:F{$lastRow}")
                ->getAlignment()
                ->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT);

                $sheet->freezePane("A2"); // Freeze First Row
            },
        ];
    }

    public function title(): string
    {
        return 'Parents';
    }
}
