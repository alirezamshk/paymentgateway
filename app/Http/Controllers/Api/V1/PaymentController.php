<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Http\Requests\CreatePaymentRequest;
use App\Http\Resources\PaymentResource;
use App\Models\Client;
use App\Models\Payment;
use App\Payments\PaymentService;
use App\Payments\VerificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function __construct(
        private readonly PaymentService $payments,
        private readonly VerificationService $verification,
    ) {}

    public function store(CreatePaymentRequest $request): JsonResponse
    {
        [$payment, $created] = $this->payments->create($this->client($request), $request->validated(), $request->idempotencyKey());

        return (new PaymentResource($payment->load(['merchant', 'provider'])))
            ->response()
            ->setStatusCode($created ? 201 : 200);
    }

    public function show(Request $request, string $paymentId): PaymentResource
    {
        return new PaymentResource($this->find($request, $paymentId));
    }

    public function verify(Request $request, string $paymentId): PaymentResource
    {
        $payment = $this->verification->verify($this->find($request, $paymentId), 'api');

        return new PaymentResource($payment->load(['merchant', 'provider']));
    }

    public function cancel(Request $request, string $paymentId): PaymentResource
    {
        $payment = $this->payments->cancel($this->find($request, $paymentId), 'api');

        return new PaymentResource($payment->load(['merchant', 'provider']));
    }

    private function client(Request $request): Client
    {
        return $request->attributes->get('client');
    }

    /** Tenant-scoped lookup: another client's payment is indistinguishable from a missing one. */
    private function find(Request $request, string $paymentId): Payment
    {
        $payment = $this->client($request)->payments()
            ->with(['merchant', 'provider'])
            ->where('public_id', $paymentId)
            ->where('is_test', false)
            ->first();

        if ($payment === null) {
            throw new ApiException('PAYMENT_NOT_FOUND', 'Payment not found.', 404);
        }

        return $payment;
    }
}
