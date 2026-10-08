@extends('admin.layout')
@section('title', __('Merchants'))
@section('content')
<div class="card">
    <form class="filters" method="GET">
        <div><label>{{ __('Provider') }}</label><select name="provider"><option value="">{{ __('Any') }}</option>@foreach($providers as $p)<option value="{{ $p->code }}" @selected(request('provider') === $p->code)>{{ $p->name }}</option>@endforeach</select></div>
        <div><button>{{ __('Filter') }}</button></div>
        <div style="margin-inline-start:auto"><a class="btn" href="{{ route('admin.merchants.create') }}">{{ __('New merchant') }}</a></div>
    </form>
</div>
<div class="card">
    <table>
        <tr><th>{{ __('Client') }}</th><th>{{ __('Name') }}</th><th>{{ __('Merchant ID') }}</th><th>{{ __('Provider') }}</th><th>{{ __('Status') }}</th><th>{{ __('Default') }}</th><th></th></tr>
        @foreach($merchants as $m)
            <tr>
                <td><a href="{{ route('admin.clients.show', $m->client) }}">{{ $m->client->name }}</a></td>
                <td>{{ $m->name }}</td><td dir="ltr">{{ $m->public_id }}</td><td>{{ $m->provider->code }}</td>
                <td><span class="badge s-{{ $m->status->value }}">{{ __($m->status->value) }}</span></td>
                <td>{{ $m->is_default ? __('yes') : '' }}</td>
                <td><a href="{{ route('admin.merchants.edit', $m) }}">{{ __('Edit') }}</a></td>
            </tr>
        @endforeach
    </table>
    {{ $merchants->links() }}
</div>
@endsection
