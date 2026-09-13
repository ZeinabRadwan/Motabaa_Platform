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
    @include('pdf.includes.header')
    <h1 class="alignC">{{ __('tr.payments.Fees Reports') }}</h1>
    <br/>
    <table style="font-size: 13px;">
        <thead>
            <tr>
                <th style="width: 20%">{{ __('tr.Name') }}</th>
                <th style="width: 20%">{{ __('tr.payments.Term') }}</th>
                <th style="width: 10%">{{ __('tr.services') }}</th>
                <th style="width: 10%">{{ __('tr.payments.Amount') }}</th>
                <th style="width: 10%">{{ __('tr.payments.Paid Payment') }}</th>
                <th style="width: 15%">{{ __('tr.Notes') }}</th>
                <th style="width: 15%">{{ __('tr.Created By') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach($data['items'] as $item)
            <tr>
                <td> {{ $item['case_name'] }}</td>
                <td> {{ $item['term_name'] }}</td>
                <td> {{ $item['services_names'] }}</td>
                <td style="text-align: center"> {{ $item['amount'] }}</td>
                <td style="text-align: center"> {{ $item['paid_amount'] }}</td>
                <td> {{ $item['notes'] ? $item['notes'] : '' }}</td>
                <td> {{ $item['created_by_name'] }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @include('pdf.includes.footer')
</body>
</html>
