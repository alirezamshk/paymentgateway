<?php

namespace App\Http\Controllers\Admin;

use App\Enums\WebhookStatus;
use App\Http\Controllers\Controller;
use App\Models\WebhookDelivery;
use App\Services\AuditLogger;
use App\Webhooks\WebhookService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WebhookController extends Controller
{
    public function index(Request $request): View
    {
        $deliveries = WebhookDelivery::with(['client', 'payment'])
            ->when($request->query('status'), fn ($q, $s) => $q->where('status', $s))
            ->when($request->query('event'), fn ($q, $e) => $q->where('event', $e))
            ->latest('id')
            ->paginate(50)
            ->withQueryString();

        return view('admin.webhooks.index', ['deliveries' => $deliveries, 'statuses' => WebhookStatus::cases()]);
    }

    public function show(WebhookDelivery $delivery): View
    {
        return view('admin.webhooks.show', ['delivery' => $delivery->load(['client', 'payment'])]);
    }

    public function retry(Request $request, WebhookDelivery $delivery, WebhookService $webhooks, AuditLogger $audit): RedirectResponse
    {
        if ($delivery->status === WebhookStatus::Processing) {
            return back()->with('error', 'Delivery is currently being processed.');
        }

        $webhooks->retry($delivery);
        $audit->log('admin', $request->user()->id, 'webhook.manual_retry', $delivery->client_id, 'webhook_delivery', $delivery->public_id);

        return back()->with('status', 'Webhook re-queued.');
    }
}
