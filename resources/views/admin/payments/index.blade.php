@extends('admin.layout')
@section('title', __('Payments'))
@section('content')
<div class="card">
    <form class="filters" method="GET">
        <div><label>{{ __('Search (payment / order / reference)') }}</label><input name="q" value="{{ request('q') }}" dir="ltr"></div>
        <div><label>{{ __('Status') }}</label><select name="status"><option value="">{{ __('Any') }}</option>@foreach($statuses as $s)<option value="{{ $s->value }}" @selected(request('status') === $s->value)>{{ __($s->value) }}</option>@endforeach</select></div>
        <div><label>{{ __('Client') }}</label><select name="client"><option value="">{{ __('Any') }}</option>@foreach($clients as $c)<option value="{{ $c->public_id }}" @selected(request('client') === $c->public_id)>{{ $c->name }}</option>@endforeach</select></div>
        <div><label>{{ __('Provider') }}</label><select name="provider"><option value="">{{ __('Any') }}</option>@foreach($providers as $pr)<option value="{{ $pr->code }}" @selected(request('provider') === $pr->code)>{{ $pr->name }}</option>@endforeach</select></div>
        <div><label>{{ __('From') }}</label><input type="date" name="from" value="{{ request('from') }}"></div>
        <div><label>{{ __('To') }}</label><input type="date" name="to" value="{{ request('to') }}"></div>
        <div><button>{{ __('Filter') }}</button></div>
    </form>
</div>
<div class="card">
    @include('admin.payments._table')
    {{ $payments->links() }}
</div>
@endsection
