@extends('admin.layout')
@section('title', __('Merchants'))
@section('actions')<a class="btn" href="{{ route('admin.merchants.create') }}">@include('admin._icon', ['name' => 'plus']) {{ __('New merchant') }}</a>@endsection
@section('content')
<div class="card">
    <div class="card-body">
        <form class="filters" method="GET">
            <div class="field"><label>{{ __('Provider') }}</label><select name="provider"><option value="">{{ __('Any') }}</option>@foreach($providers as $p)<option value="{{ $p->code }}" @selected(request('provider') === $p->code)>{{ $p->name }}</option>@endforeach</select></div>
            <div class="field"><button class="btn secondary">{{ __('Filter') }}</button></div>
        </form>
    </div>
</div>
<div class="card">
    <div class="card-body flush"><div class="table-wrap">
    <table>
        <thead><tr><th>{{ __('Client') }}</th><th>{{ __('Name') }}</th><th>{{ __('Provider') }}</th><th>{{ __('Status') }}</th><th>{{ __('Default') }}</th><th></th></tr></thead>
        <tbody>
        @forelse($merchants as $m)
            <tr>
                <td><a href="{{ route('admin.clients.show', $m->client) }}">{{ $m->client->name }}</a></td>
                <td><strong>{{ $m->name }}</strong><div class="muted small mono ltr">{{ $m->public_id }}</div></td><td>{{ $m->provider->name }}</td>
                <td>@include('admin._status', ['status' => $m->status->value])</td>
                <td>@if($m->is_default)<span class="badge b-info">{{ __('Default') }}</span>@endif</td>
                <td class="num"><a class="btn ghost sm" href="{{ route('admin.merchants.edit', $m) }}">{{ __('Edit') }}</a></td>
            </tr>
        @empty
            <tr><td colspan="6" class="empty">{{ __('No merchants yet.') }}</td></tr>
        @endforelse
        </tbody>
    </table>
    </div></div>
    {{ $merchants->links('admin.pagination') }}
</div>
@endsection
