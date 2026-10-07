@extends('admin.layout')
@section('title', 'Merchants')
@section('content')
<div class="card">
    <form class="filters" method="GET">
        <div><label>Provider</label><select name="provider"><option value="">Any</option>@foreach($providers as $p)<option value="{{ $p->code }}" @selected(request('provider') === $p->code)>{{ $p->name }}</option>@endforeach</select></div>
        <div><button>Filter</button></div>
        <div style="margin-left:auto"><a class="btn" href="{{ route('admin.merchants.create') }}">New merchant</a></div>
    </form>
</div>
<div class="card">
    <table>
        <tr><th>Client</th><th>Name</th><th>Merchant ID</th><th>Provider</th><th>Status</th><th>Default</th><th></th></tr>
        @foreach($merchants as $m)
            <tr>
                <td><a href="{{ route('admin.clients.show', $m->client) }}">{{ $m->client->name }}</a></td>
                <td>{{ $m->name }}</td><td>{{ $m->public_id }}</td><td>{{ $m->provider->code }}</td>
                <td><span class="badge s-{{ $m->status->value }}">{{ $m->status->value }}</span></td>
                <td>{{ $m->is_default ? 'yes' : '' }}</td>
                <td><a href="{{ route('admin.merchants.edit', $m) }}">Edit</a></td>
            </tr>
        @endforeach
    </table>
    {{ $merchants->links() }}
</div>
@endsection
