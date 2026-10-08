<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/**
 * The signed-in admin changes their own password. Other sessions of the same admin are signed
 * out (database session driver), so a leaked session does not survive a password change.
 */
class PasswordController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function edit(): View
    {
        return view('admin.password');
    }

    public function update(Request $request): RedirectResponse
    {
        $request->validate([
            'current_password' => ['required', 'string', 'current_password'],
            'password' => ['required', 'string', 'max:255', 'confirmed', 'different:current_password', Password::min(12)],
        ], [
            'current_password.current_password' => __('The current password is incorrect.'),
        ]);

        $user = $request->user();
        $user->forceFill(['password' => $request->input('password'), 'remember_token' => null])->save();

        if (config('session.driver') === 'database') {
            DB::table(config('session.table', 'sessions'))
                ->where('user_id', $user->id)
                ->where('id', '!=', $request->session()->getId())
                ->delete();
        }
        $request->session()->regenerate();

        $this->audit->log('admin', $user->id, 'admin.password_changed');

        return redirect()->route('admin.password.edit')->with('status', __('Password changed. Other sessions were signed out.'));
    }
}
