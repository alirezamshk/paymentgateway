<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function showLogin(): View
    {
        return view('admin.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string', 'max:255'],
        ]);

        if (! Auth::attempt($credentials + ['is_admin' => true])) {
            $this->audit->log('admin', null, 'admin.login_failed', metadata: ['email' => $credentials['email']]);

            throw ValidationException::withMessages(['email' => __('Invalid credentials.')]);
        }

        $request->session()->regenerate();
        $this->audit->log('admin', Auth::id(), 'admin.login');

        return redirect()->intended(route('admin.dashboard'));
    }

    public function logout(Request $request): RedirectResponse
    {
        $this->audit->log('admin', Auth::id(), 'admin.logout');
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login');
    }
}
