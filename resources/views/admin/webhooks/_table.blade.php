<table>
    <tr><th>{{ __('Delivery') }}</th><th>{{ __('Event') }}</th><th>{{ __('Status') }}</th><th>{{ __('Attempts') }}</th><th>HTTP</th><th>{{ __('Next retry') }}</th><th>{{ __('Delivered') }}</th><th></th></tr>
    @forelse($deliveries as $d)
        <tr>
            <td><a href="{{ route('admin.webhooks.show', $d) }}" dir="ltr">{{ $d->public_id }}</a></td>
            <td dir="ltr">{{ $d->event }}</td>
            <td><span class="badge s-{{ $d->status->value }}">{{ __('webhook.'.$d->status->value) }}</span></td>
            <td>{{ $d->attempt }}</td><td>{{ $d->http_status }}</td><td dir="ltr">{{ $d->next_retry_at }}</td><td dir="ltr">{{ $d->delivered_at }}</td>
            <td>@if($d->status->value !== 'processing')<form class="inline" method="POST" action="{{ route('admin.webhooks.retry', $d) }}">@csrf<button class="secondary">{{ __('Retry') }}</button></form>@endif</td>
        </tr>
    @empty
        <tr><td colspan="8" class="muted">{{ __('No webhook deliveries.') }}</td></tr>
    @endforelse
</table>
