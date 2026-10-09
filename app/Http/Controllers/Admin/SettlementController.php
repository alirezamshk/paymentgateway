<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\LedgerEntry;
use App\Settlement\LedgerService;
use App\Support\Display;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Settlement with clients whose payments are collected on Tech-Kala's own terminal.
 * Payouts are bank transfers made manually and then recorded here.
 */
class SettlementController extends Controller
{
    public function __construct(private readonly LedgerService $ledger) {}

    public function index(): View
    {
        $rows = Client::orderBy('name')->get()->map(fn (Client $client) => [
            'client' => $client,
            'summary' => $this->ledger->summary($client),
            'last_payout' => $client->payouts()->latest('paid_on')->latest('id')->first(),
        ]);

        return view('admin.settlements.index', [
            'rows' => $rows,
            'totals' => [
                'balance' => $rows->sum(fn ($r) => $r['summary']['balance']),
                'available' => $rows->sum(fn ($r) => $r['summary']['available']),
                'commission' => $rows->sum(fn ($r) => $r['summary']['commission']),
                'paid_out' => $rows->sum(fn ($r) => $r['summary']['paid_out']),
            ],
        ]);
    }

    public function show(Request $request, Client $client): View
    {
        [$from, $to] = $this->range($request);

        $entries = $client->ledgerEntries()
            ->with(['payment', 'payout'])
            ->when($from, fn ($q) => $q->where('created_at', '>=', $from))
            ->when($to, fn ($q) => $q->where('created_at', '<=', $to))
            ->latest('id')
            ->paginate(50)
            ->withQueryString();

        return view('admin.settlements.show', [
            'client' => $client,
            'summary' => $this->ledger->summary($client),
            'entries' => $entries,
            'payouts' => $client->payouts()->with('creator')->latest('paid_on')->latest('id')->limit(20)->get(),
        ]);
    }

    public function storePayout(Request $request, Client $client): RedirectResponse
    {
        $data = $request->validate([
            'amount' => ['required', 'integer', 'min:1', 'max:10000000000000'],
            'unit' => ['required', 'in:irr,toman'],
            'bank_reference' => ['required', 'string', 'max:100'],
            'paid_on' => ['required', 'date', 'before_or_equal:today'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $amountIrr = (int) $data['amount'] * ($data['unit'] === 'toman' ? 10 : 1);

        try {
            $this->ledger->recordPayout($client, $amountIrr, trim($data['bank_reference']), Carbon::parse($data['paid_on']), $data['note'] ?? null, $request->user()->id);
        } catch (ApiException $e) {
            return back()->withInput()->withErrors(['amount' => __('error.'.$e->errorCode)]);
        }

        return back()->with('status', __('Payout of :amount recorded.', ['amount' => Display::rial($amountIrr)]));
    }

    public function storeAdjustment(Request $request, Client $client): RedirectResponse
    {
        $data = $request->validate([
            'direction' => ['required', 'in:credit,debit'],
            'amount' => ['required', 'integer', 'min:1', 'max:10000000000000'],
            'unit' => ['required', 'in:irr,toman'],
            'description' => ['required', 'string', 'max:500'],
        ]);

        $amountIrr = (int) $data['amount'] * ($data['unit'] === 'toman' ? 10 : 1) * ($data['direction'] === 'debit' ? -1 : 1);
        $this->ledger->recordAdjustment($client, $amountIrr, $data['description'], $request->user()->id);

        return back()->with('status', __('Adjustment recorded.'));
    }

    public function export(Request $request, Client $client): StreamedResponse
    {
        [$from, $to] = $this->range($request);
        $filename = 'settlement-'.$client->slug.'-'.now(Display::TIMEZONE)->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($client, $from, $to) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM so Excel shows Persian correctly
            fputcsv($out, ['date_tehran', 'type', 'amount_irr', 'description', 'payment_id', 'order_id', 'reference_number', 'payout_bank_reference', 'available_at_tehran']);

            $client->ledgerEntries()->with(['payment', 'payout'])
                ->when($from, fn ($q) => $q->where('created_at', '>=', $from))
                ->when($to, fn ($q) => $q->where('created_at', '<=', $to))
                ->orderBy('id')
                ->each(function (LedgerEntry $e) use ($out) {
                    fputcsv($out, [
                        $e->created_at?->setTimezone(Display::TIMEZONE)->format('Y-m-d H:i:s'),
                        $e->type,
                        $e->amount_irr,
                        self::cell($e->description),
                        $e->payment?->public_id,
                        self::cell($e->payment?->order_id),
                        self::cell($e->payment?->reference_number),
                        self::cell($e->payout?->bank_reference),
                        $e->available_at?->setTimezone(Display::TIMEZONE)->format('Y-m-d H:i:s'),
                    ]);
                });

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** Text that a spreadsheet would run as a formula (= + - @, tab, CR) is prefixed with '. */
    private static function cell(?string $value): ?string
    {
        return $value !== null && $value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true) ? "'".$value : $value;
    }

    /** @return array{0: ?Carbon, 1: ?Carbon} Tehran-day boundaries converted to UTC. */
    private function range(Request $request): array
    {
        $request->validate(['from' => ['nullable', 'date'], 'to' => ['nullable', 'date']]);

        return [
            $request->filled('from') ? Carbon::parse($request->input('from'), Display::TIMEZONE)->startOfDay()->utc() : null,
            $request->filled('to') ? Carbon::parse($request->input('to'), Display::TIMEZONE)->endOfDay()->utc() : null,
        ];
    }
}
