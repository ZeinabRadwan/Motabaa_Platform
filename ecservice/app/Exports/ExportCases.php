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
use Carbon\Carbon;

use App\Http\Resources\Case\SCaseResource;

class ExportCases extends BaseExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize, WithEvents, WithTitle
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

        $user = new SCaseResource($item);

        $periods[''] = 1; 
        $periods[''] = 0; 

        $period = '';
        if($user->period == 0)
            $period = 'فترة مسائية';
        else if($user->period == 1)
            $period = 'فترة صباحية';

        $gender = '';
        if($user->gender == 1)
            $gender = 'ذكر';
        else if($user->gender == 2)
            $gender = 'انثى';

        $nationality = $user->nationality;
        if($nationality && !Str::contains(__("nationalities.{$nationality}"), 'nationalities.'))
            $nationality = __("nationalities.{$nationality}");

        $disabilitiesNames = '';
        foreach ($user->disabilities as $disability) {
            $disabilitiesNames .= "{$disability->name_ar},";
        }
        $disabilitiesNames = substr($disabilitiesNames, 0, -1);

        $servicesNames = '';
        foreach ($user->services as $service) {
            $servicesNames .= "{$service->name_ar},";
        }
        $servicesNames = substr($servicesNames, 0, -1);

        $parents = '';
        foreach ($user->parents as $parent) {
            $parents .= "{$parent->name},";
        }
        $parents = substr($parents, 0, -1);

        $birthdate = null;
        if($user->birthdate)
            $birthdate = Carbon::parse($user->birthdate)->toDateString();

        return [
            'code' => $user->id,
            'name' => $user->name,
            'registration' => ($user->beneficiary_number != null && $user->beneficiary_number != '') ?  __('tr.Beneficiary') :  __('tr.Not beneficiary'),
            'beneficiary_number' => $user->beneficiary_number,
            'period' => $period,
            'gender' => $gender,
            'birthdate' => $birthdate,
            'disabilities' => $disabilitiesNames,
            'services' => $servicesNames,
            'teacher' => isset($user->teacher[0]) ? $user->teacher[0]['name'] : '',
            'natural_specialist' => isset($user->physiotherapist[0]) ? $user->physiotherapist[0]['name'] : '',
            'occupational_specialist' => isset($user->occupational_therapy[0]) ? $user->occupational_therapy[0]['name'] : '',
            'speech_specialist' => isset($user->pronunciation_speech_specialist[0]) ? $user->pronunciation_speech_specialist[0]['name'] : '',
            'psychologist' => isset($user->psychotherapist[0]) ? $user->psychotherapist[0]['name'] : '',
            'id_or_residence_number' => $user->id_or_residence_number,
            'nationality' => $nationality,
            'parents' => $parents,
            'emergency_contact' => $user->emergency_contact,
            'phone' => $user->phone,
            'blood_type' => $user->blood_type,
            'address_number' => $user->address_number,
            'address_unit' => $user->address_unit,
            'address_building' => $user->address_building,
            'address_street' => $user->address_street,
            'address_area' => $user->address_area,
            'address_city' => $user->address_city,
            'address_zipcode' => $user->address_zipcode,
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

                $sheet->freezePane("A2"); // Freeze First Row
            },
        ];
    }

    public function title(): string
    {
        return 'Users';
    }
}
