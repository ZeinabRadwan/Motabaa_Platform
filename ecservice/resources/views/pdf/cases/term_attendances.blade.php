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
            text-align: center;
        }
        td {
            text-align: right;
        }
    </style>
    <body>
        @include('pdf.includes.header')
    
        <h1 style="text-align: center;">@lang('tr.cases_attendance_report')</h1>

        <table>
            <tbody>
                <tr>
                    <th width="15%">@lang('tr.from')</th>
                    <td width="35%">{{ $data['date_from'] }}</td>
                    <th width="15%">@lang('tr.to')</th>
                    <td width="35%">{{ $data['date_to'] }}</td>
                </tr>
            </tbody>
        </table>

        <br/>

        <table>
            <thead>
                <tr>
                    <th width="40%">@lang('tr.name')</th>
                    <th width="30%">@lang('tr.attendances.attend')</th>
                    <th width="30%">@lang('tr.attendances.absent')</th>
                </tr>
            </thead>
            <tbody>
                @foreach($data['cases'] as $case)
                    <tr>
                        <td>{{ $case->name }}</td>
                        <td style='text-align: center'>{{ $case->attend>0 ? $case->attend : '' }}</td>
                        <td style='text-align: center'>{{ $case->absent>0 ? $case->absent : '' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        @include('pdf.includes.footer')
    </body>
</html>
