<?php

namespace App\Http\Controllers\Admin;

use App\Enums\RecordStatus;
use App\Exceptions\ApiException;
use App\Gateways\GatewayManager;
use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\GatewayProvider;
use App\Models\Merchant;
use App\Services\MerchantService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MerchantController extends Controller
{
    public function __construct(private readonly MerchantService $merchants, private readonly GatewayManager $gateways) {}

    public function index(Request $request): View
    {
        $merchants = Merchant::with(['client', 'provider'])
            ->when($request->query('client'), fn ($q, $c) => $q->whereHas('client', fn ($q) => $q->where('public_id', $c)))
            ->when($request->query('provider'), fn ($q, $p) => $q->whereHas('provider', fn ($q) => $q->where('code', $p)))
            ->orderBy('client_id')->orderBy('name')
            ->paginate(25)
            ->withQueryString();

        return view('admin.merchants.index', ['merchants' => $merchants, 'providers' => GatewayProvider::orderBy('name')->get()]);
    }

    public function create(Request $request): View
    {
        return view('admin.merchants.form', $this->formData(new Merchant) + ['selectedClient' => $request->query('client')]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request, true);
        $client = Client::where('public_id', $data['client'])->firstOrFail();

        try {
            $merchant = $this->merchants->create($client, $data, 'admin', $request->user()->id);
        } catch (ApiException $e) {
            return back()->withInput($request->except('credentials'))->withErrors(['credentials' => __('error.'.$e->errorCode).' '.implode(', ', $e->details['missing'] ?? [])]);
        }

        return redirect()->route('admin.clients.show', $client)->with('status', __('Merchant :name created.', ['name' => $merchant->name]));
    }

    public function edit(Merchant $merchant): View
    {
        return view('admin.merchants.form', $this->formData($merchant->load(['client', 'provider'])) + ['selectedClient' => $merchant->client->public_id]);
    }

    public function update(Request $request, Merchant $merchant): RedirectResponse
    {
        try {
            $this->merchants->update($merchant, $this->validated($request, false), 'admin', $request->user()->id);
        } catch (ApiException $e) {
            return back()->withErrors(['credentials' => __('error.'.$e->errorCode).' '.implode(', ', $e->details['missing'] ?? [])]);
        }

        return redirect()->route('admin.clients.show', $merchant->client)->with('status', __('Merchant updated.'));
    }

    public function toggle(Request $request, Merchant $merchant): RedirectResponse
    {
        $this->merchants->setStatus($merchant, $merchant->isActive() ? RecordStatus::Disabled : RecordStatus::Active, 'admin', $request->user()->id);

        return back()->with('status', __('Merchant status changed.'));
    }

    public function makeDefault(Request $request, Merchant $merchant): RedirectResponse
    {
        $this->merchants->setDefault($merchant, 'admin', $request->user()->id);

        return back()->with('status', __('Default merchant changed.'));
    }

    public function test(Request $request, Merchant $merchant): RedirectResponse
    {
        $result = $this->merchants->testCredentials($merchant, 'admin', $request->user()->id);

        return back()->with($result->successful ? 'status' : 'error', __($result->message));
    }

    private function formData(Merchant $merchant): array
    {
        $providers = GatewayProvider::orderBy('name')->get()->filter(fn ($p) => $this->gateways->has($p->code));

        return [
            'merchant' => $merchant,
            'clients' => Client::orderBy('name')->get(),
            'providers' => $providers,
            'requirements' => $providers->mapWithKeys(fn ($p) => [$p->code => $this->gateways->for($p)->requiredCredentials()]),
        ];
    }

    private function validated(Request $request, bool $creating): array
    {
        $data = $request->validate([
            'client' => [$creating ? 'required' : 'prohibited', 'string', 'exists:clients,public_id'],
            'name' => ['required', 'string', 'max:255'],
            'provider' => ['required', 'string', 'exists:gateway_providers,code'],
            'credentials' => ['sometimes', 'array'],
            'credentials.*' => ['nullable', 'string', 'max:2000'],
            'extra_config' => ['nullable', 'json', 'max:4000'],
            'is_default' => ['sometimes', 'boolean'],
        ]);

        $extra = json_decode($data['extra_config'] ?? '', true);
        $data['credentials'] = array_merge($data['credentials'] ?? [], is_array($extra) ? $extra : []);
        unset($data['extra_config']);

        return $data;
    }
}
