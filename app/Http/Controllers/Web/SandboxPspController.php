<?php

namespace App\Http\Controllers\Web;

use App\Gateways\Adapters\SandboxGateway;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * Fake PSP page for the sandbox provider. Only routed when the sandbox is enabled
 * outside production.
 */
class SandboxPspController extends Controller
{
    public function show(string $token): Response
    {
        $state = Cache::get(SandboxGateway::CACHE_PREFIX.$token);
        abort_unless(is_array($state), 404);

        return response()->view('pay.sandbox', ['token' => $token, 'state' => $state]);
    }

    public function complete(Request $request, string $token): Response
    {
        $state = SandboxGateway::complete($token, $request->input('outcome') === 'success');
        abort_unless(is_array($state), 404);

        $separator = str_contains($state['callback_url'], '?') ? '&' : '?';

        return redirect()->away($state['callback_url'].$separator.http_build_query([
            'token' => $token,
            'status' => $state['outcome'] === 'success' ? 'OK' : 'NOK',
        ]), 303);
    }
}
