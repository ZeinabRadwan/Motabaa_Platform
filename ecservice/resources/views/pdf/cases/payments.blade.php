<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>مركز التميز الشامل للرعاية النهارية</title>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    @include('pdf.includes.css')
</head>
<style>
    th {
        background-color: white;
        text-align: right;
    }
    td {
        text-align: right;
    }
</style>
<body>
    @include('pdf.includes.header')   
    <table>
        <tbody>
            <tr>
                <th width="250px">الأسم</th>
                <td>
                    @if($data['case']->name)
                        @if(Str::contains(__("tr.{$data['case']->name}"), 'tr.'))
                            {{ $data['case']->name }}
                        @else
                            {{ __("tr.{$data['case']->name}") }}
                        @endif
                    @endif
                </td>
            <tr>
            <tr>
                <th width="250px">رقم المستفيد</th>
                <td>
                    @if($data['case']->beneficiary_number)
                        @if(Str::contains(__("tr.{$data['case']->beneficiary_number}"), 'tr.'))
                            {{ $data['case']->beneficiary_number }}
                        @else
                            {{ __("tr.{$data['case']->beneficiary_number}") }}
                        @endif
                    @endif
                </td>
            <tr>
            <tr>
                <th>رقم السجل المدني / الاقامة</th>
                <td>
                    @if($data['case']->id_or_residence_number)
                        @if(Str::contains(__("tr.{$data['case']->id_or_residence_number}"), 'tr.'))
                            {{ $data['case']->id_or_residence_number }}
                        @else
                            {{ __("tr.{$data['case']->id_or_residence_number}") }}
                        @endif
                    @endif
                </td>
            <tr>
            <tr>
                <th>الجنسية</th>
                <td>
                    @if($data["case"]->nationality)
                        @if(Str::contains(__("nationalities.{$data["case"]->nationality}"), 'nationalities.'))
                            {{ $data["case"]->nationality }}
                        @else
                            {{ __("nationalities.{$data["case"]->nationality}") }}
                        @endif
                    @endif
                    </td>
            <tr>
            <tr>
                <th>عمر</th>
                <td>
                    @if($data['case']->age)
                        @if(Str::contains(__("tr.{$data['case']->age}"), 'tr.'))
                            {{ $data['case']->age }}
                        @else
                            {{ __("tr.{$data['case']->age}") }}
                        @endif
                    @endif
                </td>
            <tr>
            <tr>
                <th>تاريخ الميلاد</th>
                <td>
                    @if($data['case']->birthdate)
                        @if(Str::contains(__("tr.{$data['case']->birthdate}"), 'tr.'))
                            {{ $data['case']->birthdate }}
                        @else
                            {{ __("tr.{$data['case']->birthdate}") }}
                        @endif
                    @endif
                </td>
            <tr>
            <tr>
                <th>الفترة</th>
                <td>
                    @if($data['case']->period)
                        @if(Str::contains(__("tr.{$data['case']->period}"), 'tr.'))
                            {{ $data['case']->period }}
                        @else
                            {{ __("tr.{$data['case']->period}") }}
                        @endif
                    @endif
                </td>
            <tr>
            <tr>
                <th>فصيلة الدم</th>
                <td>
                    @if($data['case']->blood_type)
                        @if(Str::contains(__("tr.{$data['case']->blood_type}"), 'tr.'))
                            {{ $data['case']->blood_type }}
                        @else
                            {{ __("tr.{$data['case']->blood_type}") }}
                        @endif
                    @endif
                </td>
            <tr>
            <tr>
                <th>أنواع الإعاقات</th>
                <td>
                    @if($data['case']->disability_type_names)
                        @foreach($data['case']->disability_type_names as $disabilityTypeName)
                            @if(Str::contains(__("tr.{$disabilityTypeName}"), 'tr.'))
                                {{ $disabilityTypeName }}
                            @else
                                {{ __("tr.{$disabilityTypeName}") }}
                            @endif
                            @if(!$loop->last)
                                , 
                            @endif
                        @endforeach
                    @endif
                </td>
            <tr>
            <tr>
                <th>الخدمات المقدمة</th>
                <td>
                    @if($data['case']->services)
                        @foreach($data['case']->services as $service)
                            @if(Str::contains(__("tr.{$service->name_ar}"), 'tr.'))
                                {{ $service->name_ar }}
                            @else
                                {{ __("tr.{$service->name_ar}") }}
                            @endif
                            @if(!$loop->last)
                                , 
                            @endif
                        @endforeach
                    @endif
                </td>
            <tr>
            <tr>
                <th>ولي امر</th>
                <td>
                    @if($data['case']->parents)
                        @foreach($data['case']->parents as $parent)
                            @if(Str::contains(__("tr.{$parent->name}"), 'tr.'))
                                {{ $parent->name }}
                            @else
                                {{ __("tr.{$parent->name}") }}
                            @endif
                            @if(!$loop->last)
                                , 
                            @endif
                        @endforeach
                    @endif
                </td>
            <tr>
            <tr>
                <th>المعلمة</th>
                <td>
                    @if($data['case']->teacher)
                        @foreach($data['case']->teacher as $teacher)
                            @if(Str::contains(__("tr.{$teacher->name}"), 'tr.'))
                                {{ $teacher->name }}
                            @else
                                {{ __("tr.{$teacher->name}") }}
                            @endif
                            @if(!$loop->last)
                                , 
                            @endif
                        @endforeach
                    @endif
                </td>
            <tr>
            <tr>
                <th>أخصائي العلاج الطبيعي</th>
                <td>
                    @if($data['case']->physiotherapist)
                        @foreach($data['case']->physiotherapist as $physiotherapist)
                            @if(Str::contains(__("tr.{$physiotherapist->name}"), 'tr.'))
                                {{ $physiotherapist->name }}
                            @else
                                {{ __("tr.{$physiotherapist->name}") }}
                            @endif
                            @if(!$loop->last)
                                , 
                            @endif
                        @endforeach
                    @endif
                </td>
            <tr>
            <tr>
                <th>أخصائي العلاج الوظيفي</th>
                <td>
                    @if($data['case']->occupational_therapy)
                        @foreach($data['case']->occupational_therapy as $occupational_therapy)
                            @if(Str::contains(__("tr.{$occupational_therapy->name}"), 'tr.'))
                                {{ $occupational_therapy->name }}
                            @else
                                {{ __("tr.{$occupational_therapy->name}") }}
                            @endif
                            @if(!$loop->last)
                                , 
                            @endif
                        @endforeach
                    @endif
                </td>
            <tr>
            <tr>
                <th>أخصائي العلاج النفسي</th>
                <td>
                    @if($data['case']->psychotherapist)
                        @foreach($data['case']->psychotherapist as $psychotherapist)
                            @if(Str::contains(__("tr.{$psychotherapist->name}"), 'tr.'))
                                {{ $psychotherapist->name }}
                            @else
                                {{ __("tr.{$psychotherapist->name}") }}
                            @endif
                            @if(!$loop->last)
                                , 
                            @endif
                        @endforeach
                    @endif
                </td>
            <tr>
            <tr>
                <th>أخصائي نطق وتخاطب</th>
                <td>
                    @if($data['case']->pronunciation_speech_specialist)
                        @foreach($data['case']->pronunciation_speech_specialist as $pronunciation_speech_specialist)
                            @if(Str::contains(__("tr.{$pronunciation_speech_specialist->name}"), 'tr.'))
                                {{ $pronunciation_speech_specialist->name }}
                            @else
                                {{ __("tr.{$pronunciation_speech_specialist->name}") }}
                            @endif
                            @if(!$loop->last)
                                , 
                            @endif
                        @endforeach
                    @endif
                </td>
            <tr>
            <tr>
                <th>جهه الاتصال في حالة الطوارئ</th>
                <td>
                    @if($data['case']->emergency_contact)
                        @if(Str::contains(__("tr.{$data['case']->emergency_contact}"), 'tr.'))
                            {{ $data['case']->emergency_contact }}
                        @else
                            {{ __("tr.{$data['case']->emergency_contact}") }}
                        @endif
                    @endif
                </td>
            <tr>
            <tr>
                <th>رقم الجوال</th>
                <td>
                    @if($data['case']->phone)
                        @if(Str::contains(__("tr.{$data['case']->phone}"), 'tr.'))
                            {{ $data['case']->phone }}
                        @else
                            {{ __("tr.{$data['case']->phone}") }}
                        @endif
                    @endif
                </td>
            <tr>
            <tr>
                <th width="250px">الامراض التي يعاني منها الطفل/ة؟</th>
                <td>
                    @if($data['questions']['diseasesChildSuffer'])
                        @if(Str::contains(__("tr.{$data['questions']['diseasesChildSuffer']}"), 'tr.'))
                            {{ $data['questions']['diseasesChildSuffer'] }}
                        @else
                            {{ __("tr.{$data['questions']['diseasesChildSuffer']}") }}
                        @endif
                    @endif
                </td>
            </tr>
            <tr>
                <th width="250px">العمليات الجراحية التي خضع/ت لها الطفل/ة ؟</th>
                <td>
                    @if($data['questions']['surgeriesChild'])
                        @if(Str::contains(__("tr.{$data['questions']['surgeriesChild']}"), 'tr.'))
                            {{ $data['questions']['surgeriesChild'] }}
                        @else
                            {{ __("tr.{$data['questions']['surgeriesChild']}") }}
                        @endif
                    @endif
                </td>
            </tr>
            <tr>
                <th width="250px">نمو الطفل/ة أثناء الولادة</th>
                <td>
                    @if($data['questions']['growthDuringBirth'])
                        @if(Str::contains(__("tr.{$data['questions']['growthDuringBirth']}"), 'tr.'))
                            {{ $data['questions']['growthDuringBirth'] }}
                        @else
                            {{ __("tr.{$data['questions']['growthDuringBirth']}") }}
                        @endif
                    @endif
                </td>
            </tr>
            <tr>
                <th width="250px">الادوية المستعملة</th>
                <td>
                    @if($data['questions']['usedMedicines'])
                        @if(Str::contains(__("tr.{$data['questions']['usedMedicines']}"), 'tr.'))
                            {{ $data['questions']['usedMedicines'] }}
                        @else
                            {{ __("tr.{$data['questions']['usedMedicines']}") }}
                        @endif
                    @endif
                </td>
            </tr>
            <tr>
                <th width="250px">الحالة الاجتماعية للاسرة</th>
                <td>
                    @if($data['questions']['familyStatus'])
                        @if(Str::contains(__("tr.{$data['questions']['familyStatus']}"), 'tr.'))
                            {{ $data['questions']['familyStatus'] }}
                        @else
                            {{ __("tr.{$data['questions']['familyStatus']}") }}
                        @endif
                    @endif
                </td>
            </tr>
            <tr>
                <th width="250px">صحة الام اثناء الولادة</th>
                <td>
                    @if($data['questions']['momHealthChildbirth'])
                        @if(Str::contains(__("tr.{$data['questions']['momHealthChildbirth']}"), 'tr.'))
                            {{ $data['questions']['momHealthChildbirth'] }}
                        @else
                            {{ __("tr.{$data['questions']['momHealthChildbirth']}") }}
                        @endif
                    @endif
                </td>
            </tr>
            <tr>
                <th width="250px">الحالة الاقتصادية للاسرة</th>
                <td>
                    @if($data['questions']['economicFamilyStatus'])
                        @if(Str::contains(__("tr.{$data['questions']['economicFamilyStatus']}"), 'tr.'))
                            {{ $data['questions']['economicFamilyStatus'] }}
                        @else
                            {{ __("tr.{$data['questions']['economicFamilyStatus']}") }}
                        @endif
                    @endif
                </td>
            </tr>
            <tr>
                <th width="250px">النمو الحركي</th>
                <td>
                    @if($data['questions']['motorGrowth'])
                        @if(Str::contains(__("tr.{$data['questions']['motorGrowth']}"), 'tr.'))
                            {{ $data['questions']['motorGrowth'] }}
                        @else
                            {{ __("tr.{$data['questions']['motorGrowth']}") }}
                        @endif
                    @endif
                </td>
            </tr>
            <tr>
                <th width="250px">عمر الام اثناء الولادة</th>
                <td>
                    @if($data['questions']['motherAgeAtBirth'])
                        @if(Str::contains(__("tr.{$data['questions']['motherAgeAtBirth']}"), 'tr.'))
                            {{ $data['questions']['motherAgeAtBirth'] }}
                        @else
                            {{ __("tr.{$data['questions']['motherAgeAtBirth']}") }}
                        @endif
                    @endif
                </td>
            </tr>
            <tr>
                <th width="250px">وزن الطفل عند الولادة</th>
                <td>
                    @if($data['questions']['babyWeigh'])
                        @if(Str::contains(__("tr.{$data['questions']['babyWeigh']}"), 'tr.'))
                            {{ $data['questions']['babyWeigh'] }}
                        @else
                            {{ __("tr.{$data['questions']['babyWeigh']}") }}
                        @endif
                    @endif
                </td>
            </tr>
            <tr>
                <th width="250px">نوع الولادة</th>
                <td>
                    @if($data['questions']['birthType'])
                        @if(Str::contains(__("tr.{$data['questions']['birthType']}"), 'tr.'))
                            {{ $data['questions']['birthType'] }}
                        @else
                            {{ __("tr.{$data['questions']['birthType']}") }}
                        @endif
                    @endif
                </td>
            </tr>
        </tbody>
    </table>
    @include('pdf.includes.footer')
</body>
</html>
