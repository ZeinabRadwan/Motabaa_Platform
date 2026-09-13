<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>My PDF Document</title>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <style>
        /* Define your table styles here */
        @font-face {
            font-family: 'Amiri';
            src: local('Amiri'), url('{{ public_path("fonts/Amiri-Regular.ttf") }}') format("truetype");
            font-weight: normal;
            font-style: normal;
        }
        @font-face {
            font-family: 'IBMPlexSansArabic';
            src: local('IBMPlexSansArabic'), url('{{ public_path("fonts/IBMPlexSansArabic-Regular.ttf") }}') format("truetype");
            font-weight: normal;
            font-style: normal;
        }

        
        @font-face {
            font-family: 'Amiri-bold';
            src: local('Amiri'), url('{{ public_path("fonts/Amiri-Bold.ttf") }}') format("truetype");
            font-weight: bold;
            font-style: 400;
        }



        html { font-family: 'Amiri', 'Amiri-bold', sans-serif; direction: rtl }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th, td {
            border: 1px solid #ccc;
            padding: 8px;
            text-align: center;
        }


        /* Specify that the table header should be repeated on every page */
        thead {
            display: table-header-group;
            background-color: pink;
        }

        th {
            background-color: pink;
        }

        .logo {
            position: fixed;
            top: -130px;
            right: 0
        }

        .footert {
            position: fixed;
                top: 0px;
                left: 0px;
                right: 0px;
                height: 150px;
                bottom: 0px;
                margin-bottom: -150px;
        }
        .rtl-text {
            direction: rtl; /* Right-to-left direction */
            text-align: right; /* Right-align text */
        }
        .footer {
            position: fixed;
                left: 0px;
                right: 0px;
                height: 150px;
                bottom: 0px;
                margin-bottom: -150px;
        }
        @page { margin: 150px 30px 90px 50px; }
    </style>
</head>
<body>

    <img src="{{$image}}" class="logo" width="120">

    <h1>الخطة والأهداف</h1>


    <table>
        <tr>



         <td>محمج محمد</td>
            <th>الحالة:</th>
          <td>محمج محمد</td>
          <th>الأخصائي:</th>
        </tr>
        <tr>
            <td>28/05/2200</td>
            <th>اخر تحديث:</th>

          <td>28/05/2200</td>
          <th>التارخ:</th>
        </tr>
        <tr>
          <td></td>
          <th style="background-color: white"></th>
          <td>555 77 855</td>
          <th>المجموع:</th>
        </tr>
      </table>
      
<br />
      
    <table>
        <thead>
            <tr>
                <th style="width: 17%">التارخ</th>

                <th tyle="width: 17%">الاهداف السلوكية</th>
                <th style="width: 17%">الهدف العام</th>
                <th style="width: 17%">المجل</th>
            </tr>
        </thead>
        <tbody>
            @foreach($data as $item)
            <tr>
                <td> {{$item['date_from']}} {{$item['date_from'] && $item['date_to'] ? 'إلى' : ''}}  {{$item['date_to']}}</td>
                {{-- <td style="text-align: right;word-wrap: break-word;white-space: pre-line;">{!! 
                    preg_replace("/^(([^ ]+ ){7})/",'$1<br/>', $item['title'])
                !!}</td> --}}
                <td style="text-align: right;word-wrap: break-word;white-space: pre-line; "> {{ $item['title'] }} 
                </td>


                <td> {{ $item['assessment_parent']?  $item['assessment_parent']['title'] : '' }}</td>
                
                <td class="rtl-text"> {{ $item['assessment_first_feild']?  $item['assessment_first_feild']['title'] : '' }} </td>

            </tr>
            @endforeach
        </tbody>
    </table>
    {{-- <div class="footert">
        شارع المؤرخ بن بشر - حي الربوة - الرياض 12816_
    </div>

    <div class="footer">
        شارع المؤرخ بن بشر - حي الربوة - الرياض 12816_
    </div> --}}

    <!-- Add more content or styling as needed -->
    <script type="text/php">
        if ( isset($pdf) ) {
            $font = $fontMetrics->get_font("Arial, sans-serif", "normal");

            $pdf->page_text(520, 767, "رقم سجل الشركة@&@", $font, 10, array(0,0,0));
            $pdf->page_text(420, 767, "1010469297", $font, 10, array(0,0,0));
            $pdf->page_text(340, 767, "CR No", $font, 10, array(0,0,0));
            $pdf->page_text(240, 767, "الرقم الضريبي@&@", $font, 10, array(0,0,0));
            $pdf->page_text(100, 767, "310267293700003", $font, 10, array(0,0,0));
            $pdf->page_text(20, 767, "Vat No", $font, 10, array(0,0,0));


            $pdf->page_text(140, 780, "info@altamayozsh.com الويب :@&@", $font, 10, array(0,0,0));
            $pdf->page_text(245, 780, "info@altamayozsh.com البريد الإلكتروني :@&@", $font, 10, array(0,0,0));
            $pdf->page_text(385, 780, "هاتف: 0112400007@&@", $font, 10, array(0,0,0));
            $pdf->page_text(205, 795, "شارع المؤرخ بن بشر - حي الربوة - الرياض 12816 @&@7341", $font, 10, array(0,0,0));
            $pdf->page_text(265, 810, "{PAGE_NUM} / {PAGE_COUNT} :الصفحة@&@", $font, 10, array(0,0,0));
        }
    </script>
</body>
</html>
