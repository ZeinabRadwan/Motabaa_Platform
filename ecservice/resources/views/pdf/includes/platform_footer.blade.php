<htmlpagefooter name="page-footer">
    <div class="hr-black"></div>
    <div class="alignR">
        <small>
            الصفحة {PAGENO} / {nb} 
        </small>
        {!! str_repeat(' &nbsp; ', 60) !!}
        <small>
            {{ $data['date'] }}
        </small>
    </div>
    <div style="padding-bottom: 10px"></div>
</htmlpagefooter>