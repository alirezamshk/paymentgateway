@extends('admin.layout')
@section('title', __('Payments'))
@section('content')
<div class="card">
    <div class="card-body">
        <form class="filters" method="GET">
            <div class="field" style="grid-column:1 / -1"><label>{{ __('Search') }}</label><input name="q" value="{{ request('q') }}" placeholder="{{ __('Payment ID, order, reference, name, description') }}"></div>
            <div class="field"><label>{{ __('Mobile') }}</label><input name="mobile" value="{{ request('mobile') }}" placeholder="0912..." dir="ltr" inputmode="tel"></div>
            <div class="field"><label>{{ __('Card (last 4 digits)') }}</label><input name="card" value="{{ request('card') }}" placeholder="1234" dir="ltr" inputmode="numeric" maxlength="19"></div>
            <div class="field"><label>{{ __('Username / name') }}</label><input name="username" value="{{ request('username') }}"></div>
            <div class="field"><label>{{ __('Status') }}</label><select name="status"><option value="">{{ __('Any') }}</option>@foreach($statuses as $s)<option value="{{ $s->value }}" @selected(request('status') === $s->value)>{{ __($s->value) }}</option>@endforeach</select></div>
            <div class="field"><label>{{ __('Client') }}</label><select name="client"><option value="">{{ __('Any') }}</option>@foreach($clients as $c)<option value="{{ $c->public_id }}" @selected(request('client') === $c->public_id)>{{ $c->name }}</option>@endforeach</select></div>
            <div class="field"><label>{{ __('Provider') }}</label><select name="provider"><option value="">{{ __('Any') }}</option>@foreach($providers as $pr)<option value="{{ $pr->code }}" @selected(request('provider') === $pr->code)>{{ $pr->name }}</option>@endforeach</select></div>
            <div class="field"><label>{{ __('From') }}</label><input type="date" name="from" value="{{ request('from') }}"></div>
            <div class="field"><label>{{ __('To') }}</label><input type="date" name="to" value="{{ request('to') }}"></div>
            <div class="field" style="flex-direction:row;gap:8px"><button class="btn">@include('admin._icon', ['name' => 'search']) {{ __('Search') }}</button>@if(request()->query())<a class="btn secondary" href="{{ route('admin.payments.index') }}">{{ __('Clear') }}</a>@endif</div>
        </form>
    </div>
</div>
<div class="card">
    <div class="card-body flush">@include('admin.payments._table')</div>
    {{ $payments->links('admin.pagination') }}
</div>
@endsection
