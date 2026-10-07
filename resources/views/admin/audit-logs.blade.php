@extends('admin.layout')
@section('title', 'Audit log')
@section('content')
<div class="card">
    <form class="filters" method="GET"><div><label>Action prefix</label><input name="action" value="{{ request('action') }}" placeholder="client.auth_failed"></div><div><button>Filter</button></div></form>
</div>
<div class="card">
    <table>
        <tr><th>Time</th><th>Actor</th><th>Action</th><th>Target</th><th>IP</th><th>Request</th><th>Details</th></tr>
        @foreach($logs as $l)
            <tr>
                <td>{{ $l->created_at }}</td><td>{{ $l->actor_type }}{{ $l->actor_id ? '#'.$l->actor_id : '' }}</td>
                <td>{{ $l->action }}</td><td>{{ $l->target_type }} {{ $l->target_id }}</td><td>{{ $l->ip }}</td>
                <td class="muted">{{ $l->request_id }}</td>
                <td>@if($l->metadata)<pre>{{ json_encode($l->metadata, JSON_UNESCAPED_SLASHES) }}</pre>@endif</td>
            </tr>
        @endforeach
    </table>
    {{ $logs->links() }}
</div>
@endsection
