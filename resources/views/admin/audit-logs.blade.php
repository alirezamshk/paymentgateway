@extends('admin.layout')
@section('title', __('Audit log'))
@section('content')
<div class="card">
    <form class="filters" method="GET"><div><label>{{ __('Action prefix') }}</label><input name="action" value="{{ request('action') }}" placeholder="client.auth_failed" dir="ltr"></div><div><button>{{ __('Filter') }}</button></div></form>
</div>
<div class="card">
    <table>
        <tr><th>{{ __('Time') }}</th><th>{{ __('Actor') }}</th><th>{{ __('Action') }}</th><th>{{ __('Target') }}</th><th>IP</th><th>{{ __('Request') }}</th><th>{{ __('Details') }}</th></tr>
        @foreach($logs as $l)
            <tr>
                <td dir="ltr">{{ $l->created_at }}</td><td>{{ __('source.'.$l->actor_type) }}{{ $l->actor_id ? ' #'.$l->actor_id : '' }}</td>
                <td dir="ltr">{{ $l->action }}</td><td dir="ltr">{{ $l->target_type }} {{ $l->target_id }}</td><td dir="ltr">{{ $l->ip }}</td>
                <td class="muted" dir="ltr">{{ $l->request_id }}</td>
                <td>@if($l->metadata)<pre>{{ json_encode($l->metadata, JSON_UNESCAPED_SLASHES) }}</pre>@endif</td>
            </tr>
        @endforeach
    </table>
    {{ $logs->links() }}
</div>
@endsection
