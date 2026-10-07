<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PaymentStatus;
use App\Enums\WebhookStatus;
use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Merchant;
use App\Models\Payment;
use App\Models\WebhookDelivery;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $since = now()->subDay();

        return view('admin.dashboard', [
            'clients' => Client::count(),
            'merchants' => Merchant::count(),
            'paid24h' => Payment::where('status', PaymentStatus::Paid->value)->where('paid_at', '>=', $since)->count(),
            'failed24h' => Payment::where('status', PaymentStatus::Failed->value)->where('updated_at', '>=', $since)->count(),
            'stuck' => Payment::whereIn('status', [PaymentStatus::CallbackReceived->value, PaymentStatus::Verifying->value])->count(),
            'webhooksFailed' => WebhookDelivery::where('status', WebhookStatus::Failed->value)->count(),
            'recent' => Payment::with(['client', 'provider'])->latest('id')->limit(15)->get(),
        ]);
    }
}
