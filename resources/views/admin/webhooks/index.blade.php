@extends('admin.layout')
@section('title', __('Webhooks'))
@section('content')
<div class="card">
    <div class="card-body">
        <form class="filters" method="GET">
            <div class="field"><label>{{ __('Status') }}</label><select name="status"><option value="">{{ __('Any') }}</option>@foreach($statuses as $s)<option value="{{ $s->value }}" @selected(request('status') === $s->value)>{{ __('webhook.'.$s->value) }}</option>@endforeach</select></div>
            <div class="field"><label>{{ __('Event') }}</label><input name="event" value="{{ request('event') }}" placeholder="payment.succeeded" dir="ltr"></div>
            <div class="field"><button class="btn secondary">{{ __('Filter') }}</button></div>
        </form>
    </div>
</div>
<div class="card">
    <div class="card-body flush">@include('admin.webhooks._table')</div>
    {{ $deliveries->links('admin.pagination') }}
</div>
@endsection
