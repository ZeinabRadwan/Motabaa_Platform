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
            text-align: right;
        }
        td {
            text-align: right;
        }
    </style>
    <body>
        @include('pdf.includes.header')
    
        <h1 style="text-align: center;">@lang('tr.Case Study Report')</h1>

        <table>
            <tbody>
                <tr>
                    <td width="100px">@lang('tr.Case name')</td>
                    <td>
                        @if($data['case']->name)
                            {{ $data['case']->name }}
                        @endif
                    </td>
                <tr>
            </tbody>
        </table>

        @if(is_array($data['case_study']))
            @foreach($data['case_study'] as $caseStudyKey => $caseStudy)
                @if($caseStudyKey == 'form')
                    @foreach($caseStudy as $studyKey => $study)
                        <h3>{{ __("tr.{$studyKey}") }}</h3>
                        <table>
                            <tbody>
                                @foreach($study as $name => $evaluation)
                                    @if($name)
                                        <tr>
                                            <td width="50%">
                                                @if(Str::contains(__("tr.{$name}"), 'tr.'))
                                                    {{ $name }}
                                                @else
                                                    {{ __("tr.{$name}") }}
                                                @endif
                                            </td>
                                            <td width="50%">
                                                @if($evaluation)
                                                    @if(is_array($evaluation))
                                                        @foreach($evaluation as $name => $moreEvaluation)
                                                            @if(Str::contains(__("tr.{$moreEvaluation}"), 'tr.'))
                                                                {{ $moreEvaluation }}
                                                            @else
                                                                {{ __("tr.{$moreEvaluation}") }}
                                                            @endif
                                                            @if(!$loop->last)
                                                                , 
                                                            @endif
                                                        @endforeach
                                                    @else
                                                        @if(Str::contains(__("tr.{$evaluation}"), 'tr.'))
                                                            {{ $evaluation }}
                                                        @else
                                                            {{ __("tr.{$evaluation}") }}
                                                        @endif
                                                    @endif
                                                @endif
                                            </td>
                                        </tr>
                                    @endif
                                @endforeach
                            </tbody>
                        </table>
                        <br/>
                    @endforeach
                @else
                    <h3>{{ __("tr.{$caseStudyKey}") }}</h3>
                    <table>
                        <tbody>
                            <tr>
                                <th width="35%">الأسم</th>
                                <th width="30%">البيان</th>
                                <th width="35%">ملحوظات</th>
                            </tr>
                            @foreach($caseStudy as $studyKey => $study)
                                <tr>
                                    <td width="35%">
                                        @if(Str::contains(__("tr.{$study['name']}"), 'tr.'))
                                            {{ $study['name'] }}
                                        @else
                                            {{ __("tr.{$study['name']}") }}
                                        @endif
                                    </td>
                                    <td width="30%">
                                        @if($evaluation)
                                            @if(Str::contains(__("tr.{$study['evaluation']}"), 'tr.'))
                                                {{ $study['evaluation'] }}
                                            @else
                                                {{ __("tr.{$study['evaluation']}") }}
                                            @endif
                                        @endif
                                    </td>
                                    <td width="35%">{{ $study['notes'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            @endforeach
        @endif
        @include('pdf.includes.footer')
    </body>
</html>
