<?php

namespace App\Exports\Hr;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

/**
 * Manual attendance import workbook: the "attendance" sheet read by the import (fixed English headings, every
 * column stored as text so dates and times keep their typed form), optionally prefilled with employees, and an
 * "instructions" sheet in the user's language.
 */
class AttendanceImportTemplate implements WithMultipleSheets
{
    public const HEADINGS = ['employee_number', 'employee_name', 'attendance_date', 'check_in', 'check_out', 'shift_code', 'notes'];

    /** @param  array<int, array<int, string|null>>  $rows */
    public function __construct(private readonly array $rows = [])
    {
    }

    public function sheets(): array
    {
        return [
            new class($this->rows) implements FromArray, WithHeadings, WithTitle, WithColumnFormatting, ShouldAutoSize, WithEvents
            {
                public function __construct(private readonly array $rows)
                {
                }

                public function array(): array
                {
                    return $this->rows;
                }

                public function headings(): array
                {
                    return AttendanceImportTemplate::HEADINGS;
                }

                public function title(): string
                {
                    return 'attendance';
                }

                public function columnFormats(): array
                {
                    return array_fill_keys(['A', 'B', 'C', 'D', 'E', 'F', 'G'], NumberFormat::FORMAT_TEXT);
                }

                public function registerEvents(): array
                {
                    return [AfterSheet::class => function (AfterSheet $event) {
                        $sheet = $event->sheet->getDelegate();
                        $sheet->getStyle('A1:G1')->getFont()->setBold(true);
                        $sheet->getStyle('A1:G1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('31b261');
                        $sheet->freezePane('A2');
                    }];
                }
            },
            new class implements FromArray, WithTitle, ShouldAutoSize
            {
                public function array(): array
                {
                    return array_map(fn (string $key) => [__("tr.hr.attendance_import.instructions.{$key}")], [
                        'title', 'employee_number', 'employee_name', 'attendance_date', 'check_in', 'check_out',
                        'overnight', 'shift_code', 'notes', 'empty_rows', 'reason',
                    ]);
                }

                public function title(): string
                {
                    return 'instructions';
                }
            },
        ];
    }
}
