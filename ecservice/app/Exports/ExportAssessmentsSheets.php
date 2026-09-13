<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class ExportAssessmentsSheets extends BaseExport implements WithMultipleSheets
{
    use Exportable;

    protected $data;

    public function __construct($data) {

        $this->data = $data;
    }

    public function sheets(): array {

        $sheets = [];

        foreach ($this->data as $sheetTitle => $sheetData) {
            $sheets[] = (new ExportAssessmentsSheet($sheetData, $sheetTitle));
        }

        return $sheets;
    }
}