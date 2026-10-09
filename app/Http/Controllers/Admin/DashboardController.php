<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PaymentStatus;
use App\Enums\WebhookStatus;
use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\LedgerEntry;
use App\Models\Merchant;
use App\Models\Payment;
use App\Models\WebhookDelivery;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $since = now()->subDay();

        return view('admin.dashboard', [
            'clients' => Client::count(),
            'merchants' => Merchant::count(),
            'paid24h' => Payment::where('status', PaymentStatus::Paid->value)->where('is_test', false)->where('paid_at', '>=', $since)->count(),
            // Rials: IRT amounts × 10.
            'paidAmount24h' => (int) Payment::where('status', PaymentStatus::Paid->value)->where('is_test', false)->where('paid_at', '>=', $since)
                ->sum(DB::raw("CASE WHEN currency = 'IRT' THEN amount * 10 ELSE amount END")),
            'owed' => (int) LedgerEntry::sum('amount_irr'),
            'failed24h' => Payment::where('status', PaymentStatus::Failed->value)->where('updated_at', '>=', $since)->count(),
            'stuck' => Payment::whereIn('status', [PaymentStatus::CallbackReceived->value, PaymentStatus::Verifying->value])->count(),
            'webhooksFailed' => WebhookDelivery::where('status', WebhookStatus::Failed->value)->count(),
            'recent' => Payment::with(['client', 'provider'])->latest('id')->limit(15)->get(),
        ]);
    }
}
