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
    
        <h1 style="text-align: center;">@lang('tr.centers_payments_report')</h1>

        <table>
            <thead>
                <tr>
                    <th width="40%">@lang('tr.centers.user_name')</th>
                    <th width="30%">@lang('tr.centers.amount')</th>
                    <th width="30%">@lang('tr.centers.date')</th>
                    <th width="30%">@lang('tr.centers.subscription')</th>
                </tr>
            </thead>
            <tbody>
                @foreach($data['items'] as $item)
                    <tr>
                        <td>{{ $item->user ? $item->user->name : '' }}</td>
                        <td style='text-align: center'>{{ $item->amount }}</td>
                        <td style='text-align: center'>{{ $item->date }}</td>
                        <td>{{ $item->package->title }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        @include('pdf.includes.footer')
    </body>
</html>
