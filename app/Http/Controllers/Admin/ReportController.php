<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Reports\SalesReport;
use App\Reports\StackedColumnChart;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReportController extends Controller
{
    public function sales(Request $request, SalesReport $sales): View
    {
        $filters = $request->validate([
            'period' => ['nullable', 'in:day,week,month'],
            'group' => ['nullable', 'in:provider,client'],
            'client' => ['nullable', 'string', 'max:40'],
        ]);

        $client = ! empty($filters['client']) ? Client::where('public_id', $filters['client'])->first() : null;
        $report = $sales->build($filters['period'] ?? 'day', $filters['group'] ?? 'provider', $client?->id, app()->getLocale());

        return view('admin.reports.sales', [
            'report' => $report,
            'chart' => StackedColumnChart::build($report),
            'clients' => Client::orderBy('name')->get(['public_id', 'name']),
            'selectedClient' => $client,
        ]);
    }
}
