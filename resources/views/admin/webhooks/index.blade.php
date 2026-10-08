@extends('admin.layout')
@section('title', __('Webhooks'))
@section('content')
<div class="card">
    <form class="filters" method="GET">
        <div><label>{{ __('Status') }}</label><select name="status"><option value="">{{ __('Any') }}</option>@foreach($statuses as $s)<option value="{{ $s->value }}" @selected(request('status') === $s->value)>{{ __('webhook.'.$s->value) }}</option>@endforeach</select></div>
        <div><label>{{ __('Event') }}</label><input name="event" value="{{ request('event') }}" placeholder="payment.succeeded" dir="ltr"></div>
        <div><button>{{ __('Filter') }}</button></div>
    </form>
</div>
<div class="card">
    @include('admin.webhooks._table')
    {{ $deliveries->links() }}
</div>
@endsection
