@extends('admin.layout')
@section('title', 'Providers')
@section('content')
<div class="card">
    <table>
        <tr><th>Name</th><th>Code</th><th>Adapter</th><th>Status</th><th>Merchants</th><th>Config</th><th></th></tr>
        @foreach($providers as $p)
            <tr>
                <td>{{ $p->name }}</td><td>{{ $p->code }}</td>
                <td>{{ in_array($p->code, $available) ? 'available' : 'not available' }}</td>
                <td><span class="badge s-{{ $p->status->value }}">{{ $p->status->value }}</span></td>
                <td>{{ $p->merchants_count }}</td>
                <td><pre>{{ json_encode($p->config, JSON_UNESCAPED_SLASHES) }}</pre></td>
                <td>
                    <a href="{{ route('admin.providers.edit', $p) }}">Configure</a>
                    <form class="inline" method="POST" action="{{ route('admin.providers.toggle', $p) }}">@csrf<button class="secondary">{{ $p->isActive() ? 'Disable' : 'Enable' }}</button></form>
                </td>
            </tr>
        @endforeach
    </table>
</div>
@endsection
