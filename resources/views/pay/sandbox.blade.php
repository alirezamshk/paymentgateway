@extends('pay.layout')
@section('title', 'Sandbox PSP')
@section('content')
    <h1>Sandbox PSP (test only)</h1>
    <dl>
        <dt>Amount</dt><dd>{{ number_format($state['amount']) }} {{ $state['currency'] }}</dd>
    </dl>
    <form method="POST" action="{{ route('sandbox.psp.complete', ['token' => $token]) }}">
        @csrf
        <button class="btn" name="outcome" value="success">Simulate successful payment</button>
        <button class="btn secondary" name="outcome" value="fail">Simulate failure</button>
    </form>
@endsection
