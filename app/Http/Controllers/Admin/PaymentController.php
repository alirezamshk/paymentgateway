<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\GatewayProvider;
use App\Models\Payment;
use App\Payments\VerificationService;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PaymentController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'string', 'max:30'],
            'client' => ['nullable', 'string', 'max:40'],
            'provider' => ['nullable', 'string', 'max:50'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        $payments = Payment::with(['client', 'merchant', 'provider'])
            ->when($filters['q'] ?? null, fn ($q, $t) => $q->where(fn ($q) => $q
                ->where('public_id', $t)->orWhere('order_id', $t)->orWhere('reference_number', $t)))
            ->when($filters['status'] ?? null, fn ($q, $s) => $q->where('status', $s))
            ->when($filters['client'] ?? null, fn ($q, $c) => $q->whereHas('client', fn ($q) => $q->where('public_id', $c)))
            ->when($filters['provider'] ?? null, fn ($q, $p) => $q->whereHas('provider', fn ($q) => $q->where('code', $p)))
            ->when($filters['from'] ?? null, fn ($q, $d) => $q->where('created_at', '>=', $d))
            ->when($filters['to'] ?? null, fn ($q, $d) => $q->where('created_at', '<=', $d.' 23:59:59'))
            ->latest('id')
            ->paginate(50)
            ->withQueryString();

        return view('admin.payments.index', [
            'payments' => $payments,
            'statuses' => PaymentStatus::cases(),
            'clients' => Client::orderBy('name')->get(),
            'providers' => GatewayProvider::orderBy('name')->get(),
        ]);
    }

    public function show(Payment $payment): View
    {
        $payment->load(['client', 'merchant', 'provider', 'attempts.provider', 'events', 'webhookDeliveries']);

        return view('admin.payments.show', compact('payment'));
    }

    public function verify(Request $request, Payment $payment, VerificationService $verification, AuditLogger $audit): RedirectResponse
    {
        $audit->log('admin', $request->user()->id, 'payment.manual_verify', $payment->client_id, 'payment', $payment->public_id);
        $payment = $verification->verify($payment, 'admin');

        return back()->with('status', __('Verification run. Status: :status', ['status' => __($payment->status->value)]));
    }
}
