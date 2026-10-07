@extends('admin.layout')
@section('title', 'Webhooks')
@section('content')
<div class="card">
    <form class="filters" method="GET">
        <div><label>Status</label><select name="status"><option value="">Any</option>@foreach($statuses as $s)<option @selected(request('status') === $s->value)>{{ $s->value }}</option>@endforeach</select></div>
        <div><label>Event</label><input name="event" value="{{ request('event') }}" placeholder="payment.succeeded"></div>
        <div><button>Filter</button></div>
    </form>
</div>
<div class="card">
    @include('admin.webhooks._table')
    {{ $deliveries->links() }}
</div>
@endsection
