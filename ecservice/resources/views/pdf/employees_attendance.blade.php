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
    
        <h1 style="text-align: center;">@lang('tr.employees_attendance_eport')</h1>

        <table>
            <thead>
                <tr>
                    <th width="25%">@lang('tr.name')</th>
                    <th width="15%">@lang('tr.attendance_at')</th>
                    <th width="15%">@lang('tr.status')</th>
                    <th width="15%">@lang('tr.from')</th>
                    <th width="15%">@lang('tr.to')</th>
                    <th width="15%">@lang('tr.Created By')</th>
                </tr>
            </thead>
            <tbody>
                @foreach($data['users'] as $user)
                    <tr>
                        <td>{{ $user->name }}</td>
                        <td style="text-align: center;">{{ $user->attendance_at }}</td>
                        <td style="text-align: center;">{{ ($user->status>=0) ? $data['status_labels'][$user->status] : '' }}</td>
                        <td style="text-align: center;">{{ $user->from }}</td>
                        <td style="text-align: center;">{{ $user->to }}</td>
                        <td>{{ $user->created_by_name }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        @include('pdf.includes.footer')
    </body>
</html>
