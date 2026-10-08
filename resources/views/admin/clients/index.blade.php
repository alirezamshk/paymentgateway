@extends('admin.layout')
@section('title', __('Clients'))
@section('content')
<div class="card">
    <form class="filters" method="GET">
        <div><label>{{ __('Search') }}</label><input name="q" value="{{ request('q') }}"></div>
        <div><button>{{ __('Search') }}</button></div>
        <div style="margin-inline-start:auto"><a class="btn" href="{{ route('admin.clients.create') }}">{{ __('New client') }}</a></div>
    </form>
</div>
<div class="card">
    <table>
        <tr><th>{{ __('Name') }}</th><th>{{ __('Slug') }}</th><th>{{ __('Status') }}</th><th>{{ __('Merchants') }}</th><th>{{ __('Payments') }}</th><th>{{ __('Webhook URL') }}</th></tr>
        @foreach($clients as $c)
            <tr>
                <td><a href="{{ route('admin.clients.show', $c) }}">{{ $c->name }}</a></td><td dir="ltr">{{ $c->slug }}</td>
                <td><span class="badge s-{{ $c->status->value }}">{{ __($c->status->value) }}</span></td>
                <td>{{ $c->merchants_count }}</td><td>{{ $c->payments_count }}</td><td dir="ltr">{{ $c->webhook_url }}</td>
            </tr>
        @endforeach
    </table>
    {{ $clients->links() }}
</div>
@endsection
