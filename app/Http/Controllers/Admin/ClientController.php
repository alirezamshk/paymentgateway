<?php

namespace App\Http\Controllers\Admin;

use App\Enums\RecordStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\CreatePaymentRequest;
use App\Models\Client;
use App\Models\ClientCredential;
use App\Services\AuditLogger;
use App\Services\ClientService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ClientController extends Controller
{
    public function __construct(private readonly ClientService $clients, private readonly AuditLogger $audit) {}

    public function index(Request $request): View
    {
        $clients = Client::withCount(['merchants', 'payments'])
            ->when($request->query('q'), fn ($q, $term) => $q->where(fn ($q) => $q->where('name', 'like', "%{$term}%")->orWhere('slug', 'like', "%{$term}%")))
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString();

        return view('admin.clients.index', compact('clients'));
    }

    public function create(): View
    {
        return view('admin.clients.form', ['client' => new Client]);
    }

    public function store(Request $request): RedirectResponse
    {
        $result = $this->clients->create($this->validated($request), $request->user()->id);

        return redirect()->route('admin.clients.show', $result['client'])->with('secrets', [
            'X-Client-Id' => $result['key_id'],
            'Client secret' => $result['secret'],
            'Webhook secret' => $result['webhook_secret'],
        ]);
    }

    public function show(Client $client): View
    {
        $client->load(['credentials' => fn ($q) => $q->latest('id'), 'merchants.provider']);

        return view('admin.clients.show', compact('client'));
    }

    public function edit(Client $client): View
    {
        return view('admin.clients.form', compact('client'));
    }

    public function update(Request $request, Client $client): RedirectResponse
    {
        $client->update($this->validated($request, $client));
        $this->audit->log('admin', $request->user()->id, 'client.updated', $client->id, 'client', $client->public_id);

        return redirect()->route('admin.clients.show', $client)->with('status', 'Client updated.');
    }

    public function toggle(Request $request, Client $client): RedirectResponse
    {
        $status = $client->isActive() ? RecordStatus::Disabled : RecordStatus::Active;
        $client->update(['status' => $status]);
        $this->audit->log('admin', $request->user()->id, 'client.'.($status === RecordStatus::Active ? 'enabled' : 'disabled'), $client->id, 'client', $client->public_id);

        return back()->with('status', "Client {$status->value}.");
    }

    public function issueCredential(Request $request, Client $client): RedirectResponse
    {
        [$credential, $secret] = $this->clients->issueCredential($client, $request->user()->id);

        return back()->with('secrets', ['X-Client-Id' => $credential->key_id, 'Client secret' => $secret]);
    }

    public function revokeCredential(Request $request, Client $client, ClientCredential $credential): RedirectResponse
    {
        abort_unless($credential->client_id === $client->id, 404);
        $this->clients->revokeCredential($credential, $request->user()->id);

        return back()->with('status', 'Credential revoked.');
    }

    public function rotateWebhookSecret(Request $request, Client $client): RedirectResponse
    {
        $secret = $this->clients->rotateWebhookSecret($client, $request->user()->id);

        return back()->with('secrets', ['Webhook secret' => $secret]);
    }

    private function validated(Request $request, ?Client $client = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:100', 'regex:/^[a-z0-9-]+$/', Rule::unique('clients', 'slug')->ignore($client?->id)],
            'webhook_url' => ['nullable', 'string', 'max:2048', ...CreatePaymentRequest::urlRules()],
            'return_url' => ['nullable', 'string', 'max:2048', ...CreatePaymentRequest::urlRules()],
        ]);
    }
}
