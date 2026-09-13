<htmlpagefooter name="page-footer">
    <div class="hr-black"></div>
    <small class="borderColorCCC">@lang('tr.centers.cr_number') {!! str_repeat(' &nbsp; ', 8) !!} {{ isset($data['center']->cr_number) ? $data['center']->cr_number : '' }} {!! str_repeat(' &nbsp; ', 9) !!} CR No</small>
    {!! str_repeat(' &nbsp; ', 2) !!}
    <small class="borderColorCCC">@lang('tr.centers.vat_number') {!! str_repeat(' &nbsp; ', 8) !!} {{ isset($data['center']->vat_number) ? $data['center']->vat_number : '' }}  {!! str_repeat(' &nbsp; ', 8) !!} Vat No</small>
    <div class="alignC">
        <small>
            @lang('tr.Phone Number'): {{  isset($data['center']->phone) ? $data['center']->phone : '' }} 
        </small>
        {!! str_repeat(' &nbsp; ', 3) !!}
        <small>
            @lang('tr.centers.email'): {{ isset($data['center']->email) ? $data['center']->email : '' }}
        </small>
        {!! str_repeat(' &nbsp; ', 3) !!}
        <small>
            @lang('tr.centers.url'): {{ isset($data['center']->url) ? $data['center']->url : '' }}
        </small>
    </div>
    <div class="alignC">
        <small>
            الصفحة {PAGENO} / {nb} 
        </small>
        {!! str_repeat(' &nbsp; ', 14) !!}
        <small>
            {{ isset($data['center']->city) ? $data['center']->city : '' }}
        </small>
        {!! str_repeat(' &nbsp; ', 14) !!}
        <small>
            {{now()->toDateString()}}
        </small>
    </div>
    <div style="padding-bottom: 10px"></div>
</htmlpagefooter>