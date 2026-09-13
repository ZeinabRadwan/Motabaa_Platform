<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>مركز التميز الشامل للرعاية النهارية</title>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    @include('pdf.includes.css')
</head>
<body>
    @include('pdf.includes.header')
    <h1 class="alignC">{{ $data['period_title'] ?? __('tr.goals.period_n', ['n' => ((int) ($data['period_level'] ?? 0)) + 1]) }}</h1>
    <h2 class="alignR" >الخطة والاهداف</h2>
    <table>
        <tr>
            <th style="width: 10%">الحالة</th>
            <td style="width: 40%" class="alignR">{{$data['case']->name}}</td>
            @if($data['teacher_or_specialist'])
                <th style="width: 10%">المعلمة</th>
                <td style="width: 40%" class="alignR">{{ $data['teacher_or_specialist']->name }}</td>
            @endif
        </tr>
        {{-- <tr>
            <th>اخر تحديث:</th>
            <td></td>
            <th>التاريخ:</th>
            <td></td>
        </tr>
        <tr>
            <th>المجموع:</th>
            <td></td>
            <td></td>
            <th style="background-color: white"></th>
        </tr> --}}
      </table>
<br />
    <table style="font-size: 13px;">
        <thead>
            <tr>
                <th style="width: 25%">الاهداف السلوكية</th>
                <th style="width: 20%">المجال</th>
                <th style="width: 20%">الهدف العام</th>
                <th style="width: 15%">التاريخ</th>
                <th style="width: 20%">التقيم</th>
            </tr>
        </thead>
        <tbody>
            @foreach($data['items'] as $item)
            <tr>
                <td class="rtl-text" style="word-wrap: break-word;white-space: pre-line;"> {{ $item['title'] }} {{$item['standard'] ? __($item['standard']) : ''}} {{$item['generalization'] ? __($item['generalization']) : ''}} </td>
                <td class="rtl-text"> {{ $item['assessment_first_feild']?  $item['assessment_first_feild']['title'] : '' }} </td>
                <td class="rtl-text"> {{ $item['assessment_parent']?  $item['assessment_parent']['title'] : '' }}</td>
                <td> {{ $item['date_from'] ? \Carbon\Carbon::parse($item['date_from'])->format('d-m-Y') : '' }} <br> {{ $item['date_from'] && $item['date_to'] ? 'إلى' : '' }} <br> {{ $item['date_to'] }}</td>
                @php
                    if($item['assessment_evaluation_method'] != null){
                        $dataArray = json_decode($item['assessment_evaluation_method']->items, true);
                    }else{
                        $dataArray = json_decode($data['evaluation_method']->items, true);
                    }

                    $targetValue = $item['evaluation_value'];

                    $titlesWithTargetValue = array_column(array_filter($dataArray, function($item) use ($targetValue) {
                        return $item["value"] === $targetValue;
                    }), "title");
                    $titlesWithTargetValue = $titlesWithTargetValue ? __($titlesWithTargetValue[0]) : ''
                @endphp
                <td> @lang($titlesWithTargetValue) </td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @include('pdf.includes.footer')
</body>
</html>
