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
        background-color: white;
    }
    td {
        text-align: right;
    }
</style>
<body>
    @include('pdf.includes.header')
    <h1 class="alignC">{{ __('tr.payments.payment_statement') }}</h1>
    <br/>
    <table>
        <tbody>
            <tr>
                <th width="15%">{{ __('tr.payments.Paid By Name') }}</th>
                <td>{{ $data['items'][0]['paid_by'] }}</td>
            </tr>
            <tr>
                <th width="15%">{{ __('tr.payments.Amount') }}</th>
                <td>{{ $data['items'][0]['amount'] }} {{ $data['currency'] }}</td>
            </tr>
            <tr>
                <th width="15%">{{ __('tr.payments.Paid For') }}</th>
                <td>{{ $data['items'][0]['services'] }}</td>
            </tr>
            <tr>
                <th width="15%">{{ __('tr.date') }}</th>
                <td>{{ $data['items'][0]['payment_date'] }}</td>
            </tr>
            <tr>
                <th width="15%">{{ __('tr.payments.method') }}</th>
                <td>{{ $data['items'][0]['method'] }}</td>
            </tr>
            <tr>
                <th width="15%">{{ __('tr.payments.notes') }}</th>
                <td>{{ $data['items'][0]['notes'] }}</td>
            </tr>
        </tbody>
    </table>
    <div style="position: fixed; bottom: 200;">
        <table style="border: none;">
            <tbody style="border: none;">
                <tr style="border: none;">
                    <th style="border: none;" width="33%">المستلم</th>
                    <th style="border: none;" width="33%">المحاسب</th>
                    <th style="border: none;" width="35%">المدير</th>
                </tr>
            </tbody>
        </table>
    </div>
    @include('pdf.includes.footer')
</body>
</html>
