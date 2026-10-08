@extends('admin.layout')
@section('title', __('Providers'))
@section('content')
<div class="card">
    <table>
        <tr><th>{{ __('Name') }}</th><th>{{ __('Code') }}</th><th>{{ __('Adapter') }}</th><th>{{ __('Status') }}</th><th>{{ __('Merchants') }}</th><th>{{ __('Config') }}</th><th></th></tr>
        @foreach($providers as $p)
            <tr>
                <td>{{ $p->name }}</td><td dir="ltr">{{ $p->code }}</td>
                <td>{{ in_array($p->code, $available) ? __('available') : __('not available') }}</td>
                <td><span class="badge s-{{ $p->status->value }}">{{ __($p->status->value) }}</span></td>
                <td>{{ $p->merchants_count }}</td>
                <td><pre>{{ json_encode($p->config, JSON_UNESCAPED_SLASHES) }}</pre></td>
                <td>
                    <a href="{{ route('admin.providers.edit', $p) }}">{{ __('Configure') }}</a>
                    <form class="inline" method="POST" action="{{ route('admin.providers.toggle', $p) }}">@csrf<button class="secondary">{{ $p->isActive() ? __('Disable') : __('Enable') }}</button></form>
                </td>
            </tr>
        @endforeach
    </table>
</div>
@endsection
