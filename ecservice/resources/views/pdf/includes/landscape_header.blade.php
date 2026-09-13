<htmlpageheader name="page-header">
    <div style="padding-top: 10px"></div>
    <div style="width: 100%">
        <div class="alignR floatR" style="width: 55%">
            <h2 class="fontFreeSerif marginPaddingZero uppercase colorGrey">{{ $data ? $data['center']->countryName() : '' }}</h2>
            <p class=" marginPaddingZero"><small>{{ $data ? $data['center']->commission : '' }}</small></p>
            <small class=" marginPaddingZero">{{ $data ? $data['center']->getNameAttribute() : '' }}</small>
        </div>
        <div class="alignL floatL">
            <img src="{{ $data ? $data['center']->urlLogo()['file_url'] : ''  }}" class="logo floatL" width="100px">
        </div>
    </div>
    <div style="padding-top: 5px"></div>
    <div class="hr"> </div>
    <div style="padding-bottom: 5px"></div>
</htmlpageheader>