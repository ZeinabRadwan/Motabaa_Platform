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
            <thead>
                <tr>
                    <th width="40%">@lang('tr.name')</th>
                    <th width="15%">@lang('tr.attendance_at')</th>
                    <th width="15%">@lang('tr.status')</th>
                    <th width="30%">@lang('tr.Created By')</th>
                </tr>
            </thead>
            <tbody>
                @foreach($data['cases'] as $case)
                    <tr>
                        <td>{{ $case->name }}</td>
                        <td>{{ $case->attendance_at }}</td>
                        <td>{{ ($case->status>=0) ? $data['statusLabels'][$case->status] : '' }}</td>
                        <td>{{ $case->created_by_name }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        @include('pdf.includes.footer')
    </body>
</html>
