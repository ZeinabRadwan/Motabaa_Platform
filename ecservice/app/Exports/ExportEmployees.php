<?php

namespace App\Exports;

use App\Models\User;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\FromCollection;

class ExportEmployees extends BaseExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize, WithEvents, WithTitle
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

        $first = collect($this->data)->first();
        if ($first) {
            $map = $this->mapping($first);
        }

        foreach ($map as $key => $value) {
            
            $headers[] = $key;
        }

        return $headers;
    }

    public function map($item): array {

        return $this->mapping($item);
    }

    public function mapping($item) : array {

        $user = $item instanceof User ? $item : ($item->resource ?? $item);

        $nationality = $user->nationality;
        if($nationality && !Str::contains(__("nationalities.{$nationality}"), 'nationalities.'))
            $nationality = __("nationalities.{$nationality}");

        $roles = '';
        foreach ($user->roles ?? [] as $role) {
            $roles .= "{$role->name},";
        }
        $roles = substr($roles, 0, -1);

        $department = $user->department
            ? (User::departments()[$user->department] ?? $user->department)
            : '';
        $contractType = $user->contract_type
            ? (User::contractTypes()[$user->contract_type] ?? $user->contract_type)
            : '';

        return [
            'code' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'roles' => "{$roles}",
            'nationality' => "{$nationality}",
            'id_or_residence_number' => $user->id_or_residence_number,
            'job_title' => $user->job_title,
            'department' => $department,
            'work_shift' => $user->workShiftLabel(),
            'contract_type' => $contractType,
            'hire_date' => $user->hire_date,
            'contract_end_date' => $user->contract_end_date,
            'id_expiry_date' => $user->id_expiry_date,
            'phone' => $user->phone,
            'address_unit' => $user->address_unit,
            'address_building' => $user->address_building,
            'address_street' => $user->address_street,
            'address_area' => $user->address_area,
            'address_city' => $user->address_city,
            'address_zipcode' => $user->address_zipcode,
            'address_number' => $user->address_number,
            
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
        return 'Employees';
    }
}
