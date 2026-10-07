<table>
    <tr><th>Delivery</th><th>Event</th><th>Status</th><th>Attempts</th><th>HTTP</th><th>Next retry</th><th>Delivered</th><th></th></tr>
    @forelse($deliveries as $d)
        <tr>
            <td><a href="{{ route('admin.webhooks.show', $d) }}">{{ $d->public_id }}</a></td>
            <td>{{ $d->event }}</td>
            <td><span class="badge s-{{ $d->status->value }}">{{ $d->status->value }}</span></td>
            <td>{{ $d->attempt }}</td><td>{{ $d->http_status }}</td><td>{{ $d->next_retry_at }}</td><td>{{ $d->delivered_at }}</td>
            <td>@if($d->status->value !== 'processing')<form class="inline" method="POST" action="{{ route('admin.webhooks.retry', $d) }}">@csrf<button class="secondary">Retry</button></form>@endif</td>
        </tr>
    @empty
        <tr><td colspan="8" class="muted">No webhook deliveries.</td></tr>
    @endforelse
</table>
