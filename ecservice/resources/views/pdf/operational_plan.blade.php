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
    .th_right {
        background-color: white;
        text-align: right;
    }
    .td_right {
        text-align: right;
    }
    .td_left {
        text-align: left;
    }
</style>
<body>
    @include('pdf.includes.landscape_header')
    
    @php($specialistsTotal = 0)
    @foreach($data['specialists'] as $specialist)
        @php($specialistsTotal += $specialist['users_count'])
    @endforeach
    
    @php($teachersTotal = 0)
    @foreach($data['teachers'] as $teacher)
        @php($teachersTotal += $teacher['users_count'])
    @endforeach

    <br/><br/><br/>
    <h1 class="alignC" style="font-size: 40px;">الخطة التشغيلية</h1>
    <div style="page-break-after: always;"></div>

    @if($data['information'])
        <h1 class="alignC">متطلبات بناء الخطة</h1>
        @if(isset($data['information']['form']['general_info']))
            <table style="font-size: 13px;" class="alignR">
                <tbody>
                    @foreach($data['information']['form']['general_info'] as $key => $value)
                        <tr>
                            <th width="15%">{{ __("tr.operational_plan.{$key}") }}</th>
                            <td class="td_right">{!! formatMultiline($value) !!}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            <br/>
        @endif

        @if(isset($data['information']['form']['building']))
            <h1 class="alignC">{{ __("tr.operational_plan.building") }}</h1>
            <table style="font-size: 13px;" class="alignR">
                <tbody>
                    @php($count=0)
                    @foreach($data['information']['form']['building'] as $key => $value)
                        @php($count++)
                        @if($count==1)
                            <tr>
                        @endif
                            <th width="15%"> {{ __("tr.operational_plan.{$key}") }}</th>
                            <td class="td_right"> {{ $value }}</td>
                        @if($count%2==0)
                            </tr>
                            @php($count=0)
                        @endif
                    @endforeach
                </tbody>
            </table>
            <br/>
        @endif

        @if(isset($data['information']['form']['diagnosing_reality_swot_analysis']))
            <h1 class="alignC">{{ __("tr.operational_plan.diagnosing_reality_swot_analysis") }}</h1>
            <table style="font-size: 13px;" class="alignR">
                <tbody>
                    @foreach($data['information']['form']['diagnosing_reality_swot_analysis'] as $key => $value)
                        <tr>
                            <th width="15%">{{ __("tr.operational_plan.{$key}") }}</th>
                            <td class="td_right">{!! formatMultiline($value) !!}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            <br/>
        @endif
        <div style="page-break-after: always;"></div>
    @endif

    <h1 class="alignC">أحصائية بالكوادر البشرية</h1>
    <table style="font-size: 13px;">
        <thead>
            <tr>
                @foreach($data['roles'] as $role)
                    <th>{{ $role['name'] }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            <tr>
                @foreach($data['roles'] as $role)
                    <td>{{ $role['users_count'] }}</td>
                @endforeach
            </tr>
        </tbody>
    </table>

    <br/>
    <table style="font-size: 13px;">
        <thead>
            <tr>
                <th colspan="3">{{ __('tr.operational_plan.specialists') }}</th>
            </tr>
            <tr>
                <th>{{ __('tr.operational_plan.sa') }}</th>
                <th>{{ __('tr.operational_plan.not_sa') }}</th>
                <th>{{ __('tr.operational_plan.total') }}</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                @php($saTotal = 0)
                @foreach($data['sa_specialists'] as $role)
                    @php($saTotal += $role['users_count'])
                @endforeach
                <td>{{ $saTotal }}</td>
                <td>{{ ($specialistsTotal - $saTotal) }}</td>
                <td>{{ $specialistsTotal }}</td>
            </tr>
        </tbody>
    </table>

    <br/>
    <table style="font-size: 13px;">
        <thead>
            <tr>
                <th colspan="{{ count($data['specialists']) }}">{{ __('tr.operational_plan.specialties') }}</th>
            </tr>
            <tr>
                @foreach($data['specialists'] as $specialist)
                    <th>{{ $specialist['name'] }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            <tr>
                @foreach($data['specialists'] as $specialist)
                    <td>{{ $specialist['users_count'] }}</td>
                @endforeach
            </tr>
        </tbody>
    </table>

    <br/>
    <table style="font-size: 13px;">
        <thead>
            <tr>
                <th colspan="3">{{ __('tr.operational_plan.teachers') }}</th>
            </tr>
            <tr>
                <th>{{ __('tr.operational_plan.sa') }}</th>
                <th>{{ __('tr.operational_plan.not_sa') }}</th>
                <th>{{ __('tr.operational_plan.total') }}</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                @php($saTotal = 0)
                @foreach($data['sa_teachers'] as $role)
                    @php($saTotal += $role['users_count'])
                @endforeach
                <td>{{ $saTotal }}</td>
                <td>{{ ($teachersTotal - $saTotal) }}</td>
                <td>{{ $teachersTotal }}</td>
            </tr>
        </tbody>
    </table>

    <br/>
    <table style="font-size: 13px;">
        <thead>
            <tr>
                <th colspan="{{ count($data['teachers']) }}">{{ __('tr.operational_plan.specialties') }}</th>
            </tr>
            <tr>
                @foreach($data['teachers'] as $teacher)
                    <th>{{ $teacher['name'] }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            <tr>
                @foreach($data['teachers'] as $teacher)
                    <td>{{ $teacher['users_count'] }}</td>
                @endforeach
            </tr>
        </tbody>
    </table>

    <div style="page-break-after: always;"></div>
    <h1 class="alignC">بيانات موظفي وموظفات المركز</h1>
    <table style="font-size: 13px;">
        <thead>
            <tr>
                <th>{{ __('tr.id') }}</th>
                <th>{{ __('tr.name') }}</th>
                <th>{{ __('tr.nationality') }}</th>
                <th>{{ __('tr.operational_plan.id_or_residence_number') }}</th>
                <th>{{ __('tr.phone') }}</th>
                <th>{{ __('tr.qualification') }}</th>
                <th>{{ __('tr.specialization') }}</th>
                <th>{{ __('tr.precise_specialization') }}</th>
                <th>{{ __('tr.current_work') }}</th>
                <th>{{ __('tr.on_center_sponsorship') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach($data['employees'] as $key => $employee)
            <tr>
                <td> {{ ($key+1) }}</td>
                <td class="td_right"> {{ $employee['name'] }}</td>
                <td class="td_right"> 
                    @if(Str::contains(__("nationalities.{$employee['nationality']}"), 'nationalities.'))
                        {{ $employee['nationality'] }}
                    @else
                        {{ __("nationalities.{$employee['nationality']}") }}
                    @endif
                </td>
                <td class="td_left"> {{ $employee['id_or_residence_number'] }}</td>
                <td class="td_left"> {{ $employee['phone'] }}</td>
                <td class="td_right"> {{ $employee['qualification'] }}</td>
                <td class="td_right"> {{ $employee['specialization'] }}</td>
                <td class="td_right"> {{ $employee['precise_specialization'] }}</td>
                <td class="td_right"> {{ $employee['current_work'] }}</td>
                <td> @if($employee['on_center_sponsorship']==1) {{__('tr.on_center_sponsorship') }}@else @endif</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    @foreach($data['departments'] as $key => $name)
        @php($goals = $data['plan']->goals->where('department', $key)->toArray())
        @if(count($goals)>0)
            <div style="page-break-after: always;"></div>
            
            <h1 class="alignC">الأهداف والبرامج</h1>
            <h2 class="alignC">({{ $name }})</h2>
            <table style="font-size: 13px;">
                <thead>
                    <tr>
                        <th rowspan=2>{{ __('tr.operational_plan.general_goal') }}</th>
                        <th rowspan=2>{{ __('tr.operational_plan.programs') }}</th>
                        <th rowspan=2>{{ __('tr.operational_plan.targeted_by') }}</th>
                        <th rowspan=2>{{ __('tr.operational_plan.Implemented By') }}</th>
                        <th rowspan=2>{{ __('tr.operational_plan.goals_services') }}</th>
                        <th colspan=3>{{ __('tr.operational_plan.implementation') }}</th>
                        <th rowspan=2>{{ __('tr.operational_plan.performance_indicator') }}</th>
                        <th rowspan=2>{{ __('tr.operational_plan.reference_feed') }}</th>
                    </tr>
                    <tr>
                        <th>{{ __('tr.operational_plan.implemented_at') }}</th>
                        <th>{{ __('tr.operational_plan.finished') }}</th>
                        <th>{{ __('tr.operational_plan.unfinished') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($goals as $goal)
                    <tr>
                        <td class="td_right">{!! formatMultiline($goal['general_goal']) !!}</td>
                        <td class="td_right">{!! formatMultiline($goal['activities_and_programs']) !!}</td>
                        <td class="td_right">{!! formatMultiline($goal['targeted_by']) !!}</td>
                        <td class="td_right">{!! formatMultiline($goal['implemented_by']) !!}</td>
                        <td class="td_right">{!! formatMultiline($goal['goals_services']) !!}</td>
                        <td> {{ $goal['implemented_at'] }}</td>
                        <td> @if($goal['status']==1)<div style="font-family: DejaVu Sans, sans-serif;">✔</div>@else @endif</td>
                        <td> @if($goal['status']==0)<div style="font-family: DejaVu Sans, sans-serif;">✔</div>@else @endif</td>
                        <td class="td_right">{!! formatMultiline($goal['performance_indicator']) !!}</td>
                        <td class="td_right">{!! formatMultiline($goal['reference_feed']) !!}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    @endforeach
    @include('pdf.includes.landscape_footer')
</body>
</html>
