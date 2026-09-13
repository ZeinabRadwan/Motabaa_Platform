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
        <tbody>
            <tr>
                <th style="width: 20%">{{ __('tr.Name') }}</th>
                <td> {{ $data['case']['name'] }}</td>
            </tr>
        </tbody>
    </table>
    @foreach($data['items'] as $item)
    <h3 class="alignR">{{ $item['term_name'] }} ({{ $item['services_names'] }})</h3>
    <table style="font-size: 13px;">
        <thead>
            <tr>
                <th style="width: 20%">{{ __('tr.payments.Amount') }}</th>
                <th style="width: 20%">{{ __('tr.payments.Paid Payment') }}</th>
                <th style="width: 30%">{{ __('tr.Notes') }}</th>
                <th style="width: 30%">{{ __('tr.Created By') }}</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td style="text-align: center"> {{ $item['amount'] }}</td>
                <td style="text-align: center"> {{ $item['paid_amount'] }}</td>
                <td> {{ $item['notes'] ? $item['notes'] : '' }}</td>
                <td> {{ $item['created_by_name'] }}</td>
            </tr>
        </tbody>
    </table>
    <br/>
    <table style="font-size: 13px;">
        <thead>
            <tr>
                <th style="width: 15%">{{ __('tr.payments.Batch') }}</th>
                <th style="width: 10%">{{ __('tr.payments.Payment Status') }}</th>
                <th style="width: 10%">{{ __('tr.payments.Amount') }}</th>
                <th style="width: 20%">{{ __('tr.payments.Due Date') }}</th>
                <th style="width: 20%">{{ __('tr.payments.Payment Date') }}</th>
                <th style="width: 25%">{{ __('tr.Created By') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach($item['payments'] as $payment)
            <tr>
                @php($batchName = 'App\Models\SCasePayment'::batchsList()[$payment['batch']])
                @php($statusName = 'App\Models\SCasePayment'::statusLabels()[$payment['status']])
                @php($createdBy = 'App\Models\User'::find($payment['created_by']))
                <td> {{ __("tr.payments.{$batchName}") }}</td>
                <td> {{ $statusName }}</td>
                <td style="text-align: center"> {{ $payment['amount'] }}</td>
                <td style="text-align: center"> {{ $payment['due_date'] }}</td>
                <td style="text-align: center"> {{ $payment['payment_date'] }}</td>
                <td> {{ $createdBy->name }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
    <br/>
    @endforeach
    @include('pdf.includes.footer')
</body>
</html>
