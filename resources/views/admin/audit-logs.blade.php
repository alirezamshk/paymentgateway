@extends('admin.layout')
@section('title', __('Audit log'))
@section('content')
<div class="card">
    <div class="card-body">
        <form class="filters" method="GET"><div class="field"><label>{{ __('Action prefix') }}</label><input name="action" value="{{ request('action') }}" placeholder="client.auth_failed" dir="ltr"></div><div class="field"><button class="btn secondary">{{ __('Filter') }}</button></div></form>
    </div>
</div>
<div class="card">
    <div class="card-body flush"><div class="table-wrap">
    <table>
        <thead><tr><th>{{ __('Time') }}</th><th>{{ __('Actor') }}</th><th>{{ __('Action') }}</th><th>{{ __('Target') }}</th><th>IP</th><th>{{ __('Details') }}</th></tr></thead>
        <tbody>
        @forelse($logs as $l)
            <tr>
                <td class="small ltr" style="white-space:nowrap">{{ \App\Support\Display::date($l->created_at) }}</td>
                <td>{{ __('source.'.$l->actor_type) }}{{ $l->actor_id ? ' #'.$l->actor_id : '' }}</td>
                <td class="mono ltr">{{ $l->action }}</td><td class="mono ltr small">{{ $l->target_type }} {{ $l->target_id }}</td><td class="mono ltr small">{{ $l->ip }}</td>
                <td>@if($l->metadata)<details><summary class="small">{{ __('Details') }}</summary><pre>{{ json_encode($l->metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}</pre></details>@endif</td>
            </tr>
        @empty
            <tr><td colspan="6" class="empty">{{ __('No entries.') }}</td></tr>
        @endforelse
        </tbody>
    </table>
    </div></div>
    {{ $logs->links('admin.pagination') }}
</div>
@endsection
