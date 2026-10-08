<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\GatewayProvider;
use App\Models\Payment;
use App\Payments\VerificationService;
use App\Services\AuditLogger;
use App\Support\Mobile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PaymentController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'mobile' => ['nullable', 'string', 'max:20'],
            'card' => ['nullable', 'string', 'max:20'],
            'username' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'string', 'max:30'],
            'client' => ['nullable', 'string', 'max:40'],
            'provider' => ['nullable', 'string', 'max:50'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        $payments = Payment::with(['client', 'merchant', 'provider'])
            ->when($filters['q'] ?? null, fn ($q, $t) => $this->search($q, trim($t)))
            ->when($filters['mobile'] ?? null, function ($q, $m) {
                $mobile = Mobile::forSearch($m);

                // Exact on the stored field; fall back to the description for payments created
                // before payer details were sent (e.g. "wallet top-up ... 09121234567").
                return $q->where(fn ($q) => $q->where('customer_mobile', 'like', $mobile.'%')
                    ->orWhere('description', 'like', '%'.$mobile.'%'));
            })
            ->when($filters['card'] ?? null, fn ($q, $c) => $q->where('card_last4', substr(Mobile::digits($c), -4)))
            ->when($filters['username'] ?? null, fn ($q, $u) => $q->where(fn ($q) => $q
                ->where('customer_username', 'like', trim($u).'%')
                ->orWhere('customer_name', 'like', '%'.trim($u).'%')))
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

    /** Free-text search: ids and references exactly, payer fields and description partially. */
    private function search($query, string $term)
    {
        $digits = Mobile::digits($term);

        return $query->where(function ($q) use ($term, $digits) {
            $q->where('public_id', $term)
                ->orWhere('order_id', $term)
                ->orWhere('reference_number', $term)
                ->orWhere('customer_username', 'like', $term.'%')
                ->orWhere('customer_name', 'like', '%'.$term.'%')
                ->orWhere('description', 'like', '%'.$term.'%');

            if (strlen($digits) >= 4) {
                $q->orWhere('customer_mobile', 'like', Mobile::forSearch($term).'%');
            }

            if (strlen($digits) === 4) {
                $q->orWhere('card_last4', $digits);
            }
        });
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
