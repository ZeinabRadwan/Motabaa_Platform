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
    td {
        text-align: right;
    }
</style>
<body>
    @include('pdf.includes.platform_header')
    <h1 class="alignC">{{ __('tr.centers.report') }} {{ $data['center']->title }}</h1>
    <br/>
    <table style="font-size: 13px;">
        <thead>
            <tr>
                <th style="width: 25%">{{ __('tr.centers.cases') }}</th>
                <th style="width: 25%">{{ __('tr.centers.staff') }}</th>
                <th style="width: 25%">{{ __('tr.centers.storage') }}</th>
                <th style="width: 25%">{{ __('tr.centers.whats_app_messages') }}</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td style="text-align: center"> {{ $data['n_cases'] }}</td>
                <td style="text-align: center"> {{ $data['n_staff'] }}</td>
                <td style="text-align: center"> {{ $data['storage_size'] }}</td>
                <td style="text-align: center"> {{ $data['statistics']->whats_app_messages }}</td>
            </tr>
        </tbody>
    </table>

    <br/>
    <br/>

    <h2 class="alignC">{{ __('tr.centers.payments') }}</h2>
    <br/>
    <table style="font-size: 13px;">
        <thead>
            <tr>
                <th width="40%">@lang('tr.centers.user_name')</th>
                <th width="30%">@lang('tr.centers.amount')</th>
                <th width="30%">@lang('tr.centers.date')</th>
                <th width="30%">@lang('tr.centers.subscription')</th>
            </tr>
        </thead>
        <tbody>
                @foreach($data['payments'] as $payment)
                    <tr>
                        <td>{{ $payment->user ? $payment->user->name : '' }}</td>
                        <td style='text-align: center'>{{ $payment->amount }}</td>
                        <td style='text-align: center'>{{ $payment->date }}</td>
                        <td>{{ $payment->package->title }}</td>
                    </tr>
                @endforeach
        </tbody>
    </table>
    @include('pdf.includes.platform_footer')
</body>
</html>
