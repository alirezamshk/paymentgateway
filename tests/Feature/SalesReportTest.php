<?php

namespace Tests\Feature;

use App\Enums\PaymentStatus;
use App\Models\Client;
use App\Models\GatewayProvider;
use App\Models\Payment;
use App\Models\User;
use App\Reports\SalesReport;
use App\Reports\StackedColumnChart;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

class SalesReportTest extends TestCase
{
    private Client $client;

    protected function setUp(): void
    {
        parent::setUp();
        // Thursday 1405/07/16, noon in Tehran.
        $this->travelTo(Carbon::create(2026, 10, 8, 12, 0, 0, 'Asia/Tehran'));
        $this->client = $this->makeClient()['client'];
    }

    private function pay(string $provider, int $amount, string $tehranTime, string $currency = 'IRR', string $status = 'paid', ?Client $client = null): Payment
    {
        $client ??= $this->client;
        $at = Carbon::parse($tehranTime, 'Asia/Tehran')->utc();

        $payment = new Payment([
            'client_id' => $client->id,
            'merchant_id' => ($client->merchants()->first() ?? $this->makeMerchant($client))->id,
            'provider_id' => GatewayProvider::where('code', $provider)->value('id'),
            'order_id' => 'ORD-'.Str::random(8),
            'amount' => $amount,
            'currency' => $currency,
            'return_url' => 'https://site-a.example.com/r',
            'paid_at' => $status === 'paid' ? $at : null,
        ]);
        $payment->forceFill(['status' => PaymentStatus::from($status), 'created_at' => $at])->save();

        return $payment;
    }

    private function series(array $report, string $code): array
    {
        $id = (string) GatewayProvider::where('code', $code)->value('id');

        return collect($report['series'])->firstWhere('key', $id) ?? [];
    }

    public function test_daily_totals_per_gateway_in_rials(): void
    {
        $this->pay('zarinpal', 100000, '2026-10-08 09:00');
        $this->pay('zarinpal', 5000, '2026-10-08 10:00', 'IRT');          // 50,000 IRR
        $this->pay('sepehr', 300000, '2026-10-07 23:30');                  // Tehran yesterday
        $this->pay('sepehr', 999999, '2026-10-08 11:00', status: 'failed');
        $this->pay('sepehr', 777777, '2026-08-01 10:00');                  // outside 30 days

        $r = app(SalesReport::class)->build('day', 'provider', null, 'en');

        $this->assertCount(30, $r['buckets']);
        $this->assertSame('2026-10-08', end($r['buckets'])['key']);
        $zp = (string) GatewayProvider::where('code', 'zarinpal')->value('id');
        $sp = (string) GatewayProvider::where('code', 'sepehr')->value('id');
        $this->assertSame(150000, $r['values']['2026-10-08'][$zp]);
        $this->assertSame(300000, $r['values']['2026-10-07'][$sp]);
        $this->assertSame(450000, $r['total']);
        $this->assertSame(3, $r['count']);
        $this->assertSame(1, $r['failed']);
        $this->assertSame(['total' => 150000, 'count' => 2], array_intersect_key($this->series($r, 'zarinpal'), ['total' => 1, 'count' => 1]));
    }

    public function test_weeks_start_saturday_in_persian_and_monday_in_english(): void
    {
        $this->pay('sepehr', 100000, '2026-10-04 10:00'); // Sunday

        $fa = app(SalesReport::class)->build('week', 'provider', null, 'fa');
        $en = app(SalesReport::class)->build('week', 'provider', null, 'en');

        $this->assertSame('2026-10-03', end($fa['buckets'])['key']); // Saturday
        $this->assertSame(100000, $fa['bucket_totals']['2026-10-03']);
        $this->assertSame('2026-10-05', end($en['buckets'])['key']); // Monday
        $this->assertSame(100000, $en['bucket_totals']['2026-09-28']);
    }

    public function test_months_are_jalali_in_persian_and_gregorian_in_english(): void
    {
        $this->pay('sepehr', 100000, '2026-09-22 10:00'); // 1405/06/31
        $this->pay('sepehr', 200000, '2026-09-23 10:00'); // 1405/07/01

        $fa = app(SalesReport::class)->build('month', 'provider', null, 'fa');
        $en = app(SalesReport::class)->build('month', 'provider', null, 'en');

        $this->assertSame('1405-07', end($fa['buckets'])['key']);
        $this->assertSame('مهر', end($fa['buckets'])['label']);
        $this->assertSame(200000, $fa['bucket_totals']['1405-07']);
        $this->assertSame(100000, $fa['bucket_totals']['1405-06']);
        $this->assertSame('2026-10', end($en['buckets'])['key']);
        $this->assertSame(300000, $en['bucket_totals']['2026-09']);
    }

    public function test_group_by_client_and_client_filter(): void
    {
        $other = $this->makeClient('site-b')['client'];
        $this->pay('sepehr', 100000, '2026-10-08 09:00');
        $this->pay('sepehr', 400000, '2026-10-08 09:00', client: $other);

        $byClient = app(SalesReport::class)->build('day', 'client', null, 'en');
        $this->assertSame([(string) $this->client->id, (string) $other->id], array_column($byClient['series'], 'key'));
        $this->assertSame([1, 2], array_column($byClient['series'], 'slot'));

        $filtered = app(SalesReport::class)->build('day', 'provider', $other->id, 'en');
        $this->assertSame(400000, $filtered['total']);
    }

    public function test_chart_geometry_is_built_and_empty_report_is_safe(): void
    {
        $empty = app(SalesReport::class)->build('day', 'provider', null, 'en');
        $this->assertSame(0, $empty['total']);
        $this->assertSame([], $empty['series']);
        StackedColumnChart::build($empty);

        $this->pay('sepehr', 100000, '2026-10-08 09:00');
        $chart = StackedColumnChart::build(app(SalesReport::class)->build('day', 'provider', null, 'en'));
        $this->assertNotEmpty($chart['ticks']);
    }

    public function test_admin_page_renders_in_both_languages(): void
    {
        $this->pay('sepehr', 100000, '2026-10-08 09:00');
        $admin = User::factory()->create(['is_admin' => true]);

        foreach (['fa' => 'گزارش فروش', 'en' => 'Sales report'] as $locale => $title) {
            foreach (['day', 'week', 'month'] as $period) {
                $this->actingAs($admin)->withSession(['admin_locale' => $locale])
                    ->get("/admin/reports/sales?period={$period}&client={$this->client->public_id}")
                    ->assertOk()->assertSee($title)->assertSee('<svg', false)->assertSee('100,000');
            }
        }

        $this->actingAs($admin)->get('/admin/reports/sales?period=year')->assertSessionHasErrors('period');
        auth()->logout();
        $this->get('/admin/reports/sales')->assertRedirect();
    }
}
