@extends('admin.layout')
@section('title', __('Providers'))
@section('content')
<div class="card">
    <div class="card-body flush"><div class="table-wrap">
    <table>
        <thead><tr><th>{{ __('Name') }}</th><th>{{ __('Code') }}</th><th>{{ __('Adapter') }}</th><th>{{ __('Status') }}</th><th class="num">{{ __('Merchants') }}</th><th>{{ __('Config') }}</th><th></th></tr></thead>
        <tbody>
        @foreach($providers as $p)
            <tr>
                <td><strong>{{ $p->name }}</strong></td><td class="mono ltr">{{ $p->code }}</td>
                <td>@if(in_array($p->code, $available))<span class="badge b-success">{{ __('available') }}</span>@else<span class="badge b-muted">{{ __('not available') }}</span>@endif</td>
                <td>@include('admin._status', ['status' => $p->status->value])</td>
                <td class="num">{{ $p->merchants_count }}</td>
                <td class="mono ltr small">{{ json_encode($p->config ?: new stdClass, JSON_UNESCAPED_SLASHES) }}</td>
                <td class="num" style="white-space:nowrap">
                    <a class="btn ghost sm" href="{{ route('admin.providers.edit', $p) }}">{{ __('Configure') }}</a>
                    <form class="inline" method="POST" action="{{ route('admin.providers.toggle', $p) }}">@csrf<button class="btn secondary sm">{{ $p->isActive() ? __('Disable') : __('Enable') }}</button></form>
                </td>
            </tr>
        @endforeach
        </tbody>
    </table>
    </div></div>
</div>
@endsection
