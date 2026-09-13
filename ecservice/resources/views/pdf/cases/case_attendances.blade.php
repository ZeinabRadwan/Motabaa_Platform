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
                    <th width="15%">@lang('tr.name')</th>
                    <td>{{ $data['case']->name }}</td>
                </tr>
            </tbody>
        </table>

        <br/>

        <table>
            <thead>
                <tr>
                    <th width="30%">@lang('tr.attendance_at')</th>
                    <th width="30%">@lang('tr.status')</th>
                    <th width="40%">@lang('tr.Created By')</th>
                </tr>
            </thead>
            <tbody>
                @foreach($data['attendances'] as $attendance)
                    <tr>
                        <td>{{ $attendance->attendance_at }}</td>
                        <td>{{ ($attendance->status>=0) ? $data['statusLabels'][$attendance->status] : '' }}</td>
                        <td>{{ $attendance->createdBy ? $attendance->createdBy->name : '' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        @include('pdf.includes.footer')
    </body>
</html>
