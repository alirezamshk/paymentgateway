@php $parts = []; @endphp
@if($client->commission_bps > 0) @php $parts[] = rtrim(rtrim(number_format($client->commission_bps / 100, 2, '.', ''), '0'), '.').'%'; @endphp @endif
@if($client->commission_fixed_irr > 0) @php $parts[] = \App\Support\Display::rial($client->commission_fixed_irr); @endphp @endif
@if($parts)<span class="ltr">{{ implode(' + ', $parts) }}</span>@else<span class="muted">{{ __('No commission') }}</span>@endif
