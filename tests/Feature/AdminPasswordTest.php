<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminPasswordTest extends TestCase
{
    private function admin(): User
    {
        return User::factory()->create(['is_admin' => true, 'password' => 'correct-horse-battery']);
    }

    public function test_admin_changes_own_password(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->get('/admin')->assertSee(route('admin.password.edit'));
        $this->actingAs($admin)->withSession(['admin_locale' => 'fa'])->get('/admin/password')->assertOk()->assertSee('تغییر رمز عبور');

        $this->actingAs($admin)->put('/admin/password', [
            'current_password' => 'correct-horse-battery',
            'password' => 'a-brand-new-passphrase',
            'password_confirmation' => 'a-brand-new-passphrase',
        ])->assertRedirect('/admin/password')->assertSessionHas('status')->assertSessionHasNoErrors();

        $this->assertTrue(Hash::check('a-brand-new-passphrase', $admin->fresh()->password));
        $this->assertDatabaseHas('audit_logs', ['action' => 'admin.password_changed', 'actor_id' => $admin->id]);
        $this->assertStringNotContainsString('a-brand-new-passphrase', (string) DB::table('audit_logs')->pluck('metadata')->implode(' '));

        // The new password works for login, the old one does not.
        auth()->logout();
        $this->post('/admin/login', ['email' => $admin->email, 'password' => 'correct-horse-battery'])->assertSessionHasErrors('email');
        $this->post('/admin/login', ['email' => $admin->email, 'password' => 'a-brand-new-passphrase'])->assertRedirect('/admin');
    }

    public function test_wrong_current_password_weak_or_unconfirmed_password_is_rejected(): void
    {
        $admin = $this->admin();
        $change = fn (array $data) => $this->actingAs($admin)->put('/admin/password', $data);

        $change(['current_password' => 'wrong-password', 'password' => 'a-brand-new-passphrase', 'password_confirmation' => 'a-brand-new-passphrase'])
            ->assertSessionHasErrors('current_password');
        $change(['current_password' => 'correct-horse-battery', 'password' => 'short', 'password_confirmation' => 'short'])
            ->assertSessionHasErrors('password');
        $change(['current_password' => 'correct-horse-battery', 'password' => 'a-brand-new-passphrase', 'password_confirmation' => 'something-else-entirely'])
            ->assertSessionHasErrors('password');
        $change(['current_password' => 'correct-horse-battery', 'password' => 'correct-horse-battery', 'password_confirmation' => 'correct-horse-battery'])
            ->assertSessionHasErrors('password');

        $this->assertTrue(Hash::check('correct-horse-battery', $admin->fresh()->password));
    }

    public function test_other_sessions_are_signed_out(): void
    {
        config(['session.driver' => 'database']);
        $admin = $this->admin();
        $other = User::factory()->create(['is_admin' => true]);
        DB::table('sessions')->insert([
            ['id' => 'old-session-of-admin', 'user_id' => $admin->id, 'ip_address' => null, 'user_agent' => null, 'payload' => '', 'last_activity' => time()],
            ['id' => 'session-of-other-admin', 'user_id' => $other->id, 'ip_address' => null, 'user_agent' => null, 'payload' => '', 'last_activity' => time()],
        ]);

        $this->actingAs($admin)->put('/admin/password', [
            'current_password' => 'correct-horse-battery',
            'password' => 'a-brand-new-passphrase',
            'password_confirmation' => 'a-brand-new-passphrase',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('sessions', ['id' => 'old-session-of-admin']);
        $this->assertDatabaseHas('sessions', ['id' => 'session-of-other-admin']);
    }

    public function test_guests_cannot_change_passwords(): void
    {
        $this->get('/admin/password')->assertRedirect();
        $this->put('/admin/password', ['current_password' => 'x', 'password' => 'y'])->assertRedirect();
    }
}
