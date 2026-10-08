<div class="table-wrap">
<table>
    <thead><tr><th>{{ __('Delivery') }}</th><th>{{ __('Event') }}</th><th>{{ __('Status') }}</th><th class="num">{{ __('Attempts') }}</th><th>HTTP</th><th>{{ __('Next retry') }}</th><th>{{ __('Delivered') }}</th><th></th></tr></thead>
    <tbody>
    @forelse($deliveries as $d)
        <tr>
            <td><a class="mono ltr" href="{{ route('admin.webhooks.show', $d) }}">{{ \Illuminate\Support\Str::limit($d->public_id, 18) }}</a></td>
            <td class="mono ltr">{{ $d->event }}</td>
            <td>@include('admin._status', ['status' => $d->status->value, 'prefix' => 'webhook.'])</td>
            <td class="num">{{ $d->attempt }}</td><td class="mono">{{ $d->http_status }}</td>
            <td class="small ltr">{{ $d->next_retry_at ? \App\Support\Display::date($d->next_retry_at) : '—' }}</td>
            <td class="small ltr">{{ $d->delivered_at ? \App\Support\Display::date($d->delivered_at) : '—' }}</td>
            <td class="num">@if($d->status->value !== 'processing')<form class="inline" method="POST" action="{{ route('admin.webhooks.retry', $d) }}">@csrf<button class="btn secondary sm">@include('admin._icon', ['name' => 'refresh', 'size' => 14]) {{ __('Retry') }}</button></form>@endif</td>
        </tr>
    @empty
        <tr><td colspan="8" class="empty">{{ __('No webhook deliveries.') }}</td></tr>
    @endforelse
    </tbody>
</table>
</div>
