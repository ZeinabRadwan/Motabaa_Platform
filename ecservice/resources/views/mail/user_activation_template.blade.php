<!DOCTYPE html>
<html>
    <head>
        <title>{{ $data['title'] }}</title>
    </head>
    <body style="direction: rtl;">
        <h1>عزيزي {{ $data['name'] }},</h1>
        <p>
            الرجاء الضغط على الرابط التالي للتحقق من بريدك الإلكتروني <br/>
            <a href="{{ $data['verify_link'] }}">{{ $data['verify_link'] }}</a>
        </p>

        <p>
            [هذا الرد آلي فلا ترد عليه]<br/>
            شكرًا
        </p>
        
        <p>
            المملكة العربية السعودية<br/>
            وزارة الموارد البشرية والتنمية الاجتماعية<br/>
            منصة أثر
        </p>
    </body>
</html>