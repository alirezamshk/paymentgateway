<?php

namespace App\Http\Controllers\Admin;

use App\Enums\RecordStatus;
use App\Gateways\GatewayManager;
use App\Http\Controllers\Controller;
use App\Models\GatewayProvider;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Provider configuration holds only non-secret settings (sandbox flag, endpoint overrides).
 * Secrets belong on merchants, encrypted.
 */
class ProviderController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function index(GatewayManager $gateways): View
    {
        $providers = GatewayProvider::withCount('merchants')->orderBy('name')->get();

        return view('admin.providers.index', ['providers' => $providers, 'available' => $gateways->codes()]);
    }

    public function edit(GatewayProvider $provider): View
    {
        return view('admin.providers.edit', compact('provider'));
    }

    public function update(Request $request, GatewayProvider $provider): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'config' => ['nullable', 'json', 'max:4000'],
        ]);

        $config = json_decode($data['config'] ?? '{}', true) ?: [];
        $provider->update(['name' => $data['name'], 'config' => $config]);
        $this->audit->log('admin', $request->user()->id, 'provider.updated', null, 'provider', $provider->code, ['config_keys' => array_keys($config)]);

        return redirect()->route('admin.providers.index')->with('status', 'Provider updated.');
    }

    public function toggle(Request $request, GatewayProvider $provider): RedirectResponse
    {
        $status = $provider->isActive() ? RecordStatus::Disabled : RecordStatus::Active;
        $provider->update(['status' => $status]);
        $this->audit->log('admin', $request->user()->id, 'provider.'.($status === RecordStatus::Active ? 'enabled' : 'disabled'), null, 'provider', $provider->code);

        return back()->with('status', "Provider {$status->value}.");
    }
}
