<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AdminLocaleTest extends TestCase
{
    public function test_admin_panel_is_persian_and_rtl_by_default(): void
    {
        $this->get('/admin/login')
            ->assertOk()
            ->assertSee('dir="rtl"', false)
            ->assertSee('ورود به پنل مدیریت');

        $this->actingAs(User::factory()->create(['is_admin' => true]))
            ->get('/admin')
            ->assertSee('داشبورد')
            ->assertSee('آخرین پرداخت‌ها');
    }

    public function test_admin_panel_can_be_english(): void
    {
        config(['payments.admin_locale' => 'en']);

        $this->get('/admin/login')->assertSee('dir="ltr"', false)->assertSee('Admin login');
    }

    public function test_api_errors_stay_english_regardless_of_admin_locale(): void
    {
        Http::fake(['*' => Http::response('', 200)]);
        $auth = $this->makeClient();
        $this->makeMerchant($auth['client']);

        $this->signed($auth, 'POST', '/api/v1/payments', $this->paymentBody(['amount' => 'abc']))
            ->assertStatus(422)
            ->assertJsonPath('error.message', 'The request is invalid.')
            ->assertDontSee('الزامی');
    }
}
