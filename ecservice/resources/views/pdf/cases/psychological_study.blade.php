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
    
        <h1 style="text-align: center;">@lang('tr.Psychological Study Report')</h1>

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

        @if(is_array($data['psychological_study']))
            @foreach($data['psychological_study'] as $psychologicalStudyKey => $psychologicalStudy)
                <h3>{{ __("tr.{$psychologicalStudyKey}") }}</h3>
                <table>
                    <tbody>
                        @foreach($psychologicalStudy as $name => $evaluation)
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
                                        @if(Str::contains(__("tr.{$evaluation}"), 'tr.'))
                                            {{ $evaluation }}
                                        @else
                                            {{ __("tr.{$evaluation}") }}
                                        @endif
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endforeach
        @endif
        @include('pdf.includes.footer')
    </body>
</html>
