@extends('admin.layout')
@section('title', 'Clients')
@section('content')
<div class="card">
    <form class="filters" method="GET">
        <div><label>Search</label><input name="q" value="{{ request('q') }}"></div>
        <div><button>Search</button></div>
        <div style="margin-left:auto"><a class="btn" href="{{ route('admin.clients.create') }}">New client</a></div>
    </form>
</div>
<div class="card">
    <table>
        <tr><th>Name</th><th>Slug</th><th>Status</th><th>Merchants</th><th>Payments</th><th>Webhook URL</th></tr>
        @foreach($clients as $c)
            <tr>
                <td><a href="{{ route('admin.clients.show', $c) }}">{{ $c->name }}</a></td><td>{{ $c->slug }}</td>
                <td><span class="badge s-{{ $c->status->value }}">{{ $c->status->value }}</span></td>
                <td>{{ $c->merchants_count }}</td><td>{{ $c->payments_count }}</td><td>{{ $c->webhook_url }}</td>
            </tr>
        @endforeach
    </table>
    {{ $clients->links() }}
</div>
@endsection
