@if(session('status'))<div class="alert ok">@include('admin._icon', ['name' => 'check'])<div>{{ session('status') }}</div></div>@endif
@if(session('error'))<div class="alert err">@include('admin._icon', ['name' => 'alert'])<div>{{ session('error') }}</div></div>@endif
@if($errors->any())<div class="alert err">@include('admin._icon', ['name' => 'alert'])<div>@foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach</div></div>@endif
@if(session('secrets'))
    <div class="alert secret">
        <strong>@include('admin._icon', ['name' => 'key']) {{ __('Copy these now. They will not be shown again.') }}</strong>
        @foreach(session('secrets') as $label => $value)
            <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap"><span class="muted" style="min-width:150px">{{ __($label) }}</span><span class="secret-value">{{ $value }}</span></div>
        @endforeach
    </div>
@endif
