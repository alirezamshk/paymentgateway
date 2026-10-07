@extends('admin.layout')
@section('title', 'Payments')
@section('content')
<div class="card">
    <form class="filters" method="GET">
        <div><label>Search (payment / order / reference)</label><input name="q" value="{{ request('q') }}"></div>
        <div><label>Status</label><select name="status"><option value="">Any</option>@foreach($statuses as $s)<option @selected(request('status') === $s->value)>{{ $s->value }}</option>@endforeach</select></div>
        <div><label>Client</label><select name="client"><option value="">Any</option>@foreach($clients as $c)<option value="{{ $c->public_id }}" @selected(request('client') === $c->public_id)>{{ $c->name }}</option>@endforeach</select></div>
        <div><label>Provider</label><select name="provider"><option value="">Any</option>@foreach($providers as $pr)<option value="{{ $pr->code }}" @selected(request('provider') === $pr->code)>{{ $pr->name }}</option>@endforeach</select></div>
        <div><label>From</label><input type="date" name="from" value="{{ request('from') }}"></div>
        <div><label>To</label><input type="date" name="to" value="{{ request('to') }}"></div>
        <div><button>Filter</button></div>
    </form>
</div>
<div class="card">
    @include('admin.payments._table')
    {{ $payments->links() }}
</div>
@endsection
