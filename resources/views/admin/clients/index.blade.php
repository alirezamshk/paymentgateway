@extends('admin.layout')
@section('title', __('Clients'))
@section('actions')<a class="btn" href="{{ route('admin.clients.create') }}">@include('admin._icon', ['name' => 'plus']) {{ __('New client') }}</a>@endsection
@section('content')
<div class="card">
    <div class="card-body">
        <form class="filters" method="GET"><div class="field"><label>{{ __('Search') }}</label><input name="q" value="{{ request('q') }}"></div><div class="field"><button class="btn secondary">@include('admin._icon', ['name' => 'search']) {{ __('Search') }}</button></div></form>
    </div>
</div>
<div class="card">
    <div class="card-body flush"><div class="table-wrap">
    <table>
        <thead><tr><th>{{ __('Name') }}</th><th>{{ __('Slug') }}</th><th>{{ __('Status') }}</th><th class="num">{{ __('Merchants') }}</th><th class="num">{{ __('Payments') }}</th><th>{{ __('Commission') }}</th><th>{{ __('Webhook URL') }}</th><th></th></tr></thead>
        <tbody>
        @forelse($clients as $c)
            <tr>
                <td><a href="{{ route('admin.clients.show', $c) }}"><strong>{{ $c->name }}</strong></a></td><td class="mono ltr">{{ $c->slug }}</td>
                <td>@include('admin._status', ['status' => $c->status->value])</td>
                <td class="num">{{ $c->merchants_count }}</td><td class="num">{{ number_format($c->payments_count) }}</td>
                <td>@include('admin.settlements._commission', ['client' => $c])</td>
                <td class="small ltr">{{ \Illuminate\Support\Str::limit($c->webhook_url, 48) }}</td>
                <td class="num"><a class="btn ghost sm" href="{{ route('admin.clients.edit', $c) }}">{{ __('Edit') }}</a></td>
            </tr>
        @empty
            <tr><td colspan="8" class="empty">{{ __('No clients yet.') }}</td></tr>
        @endforelse
        </tbody>
    </table>
    </div></div>
    {{ $clients->links('admin.pagination') }}
</div>
@endsection
