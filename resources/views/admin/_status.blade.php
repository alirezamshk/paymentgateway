@php
    $tone = match ($status) {
        'paid', 'delivered', 'active', 'verified' => 'b-success',
        'failed', 'disabled', 'revoked', 'cancelled', 'expired' => 'b-danger',
        'pending', 'redirected', 'processing', 'requested' => 'b-info',
        'callback_received', 'verifying', 'created' => 'b-warning',
        default => 'b-muted',
    };
@endphp
<span class="badge {{ $tone }}">{{ __(isset($prefix) ? $prefix.$status : $status) }}</span>
