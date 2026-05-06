<?php

namespace App\Livewire\Reports;

use Carbon\Carbon;
use Livewire\Component;
use App\Enums\DatePeriod;
use App\Services\ReportService;
use App\Exports\CustomerNominalExport;
use Maatwebsite\Excel\Facades\Excel;
use Barryvdh\DomPDF\Facade\Pdf;

class CustomerNominalReport extends Component
{
    public string $dateFilter = DatePeriod::THIS_MONTH->value;
    public ?string $customStartDate = null;
    public ?string $customEndDate = null;
    public string $search = '';

    public function mount(): void
    {
        $this->customStartDate = Carbon::now('Asia/Jakarta')->startOfMonth()->format('Y-m-d');
        $this->customEndDate = Carbon::now('Asia/Jakarta')->endOfMonth()->format('Y-m-d');
    }

    public function updateCustomRange(string $startDate, string $endDate): void
    {
        $this->customStartDate = $startDate;
        $this->customEndDate = $endDate;
    }

    public function refresh(): void
    {
        [$start, $end] = $this->getDateRange();
        app(ReportService::class)->clearCache($start, $end);
    }

    public function exportExcel()
    {
        [$start, $end] = $this->getDateRange();
        $rows = app(ReportService::class)->getCustomerNominal($start, $end, $this->search ?: null);
        $filename = 'customer-nominal-' . $start->format('Ymd') . '-' . $end->format('Ymd') . '.xlsx';

        return Excel::download(new CustomerNominalExport($rows, $start, $end), $filename);
    }

    public function exportPdf()
    {
        [$start, $end] = $this->getDateRange();
        $rows = app(ReportService::class)->getCustomerNominal($start, $end, $this->search ?: null);

        $pdf = Pdf::loadView('exports.pdf.customer-nominal', [
            'rows' => $rows,
            'start' => $start,
            'end' => $end,
        ])->setPaper('a4', 'portrait');

        return response()->streamDownload(
            fn () => print($pdf->output()),
            'customer-nominal-' . $start->format('Ymd') . '-' . $end->format('Ymd') . '.pdf'
        );
    }

    protected function getDateRange(): array
    {
        $now = Carbon::now('Asia/Jakarta');

        return match (DatePeriod::tryFrom($this->dateFilter)) {
            DatePeriod::TODAY => [$now->copy()->startOfDay()->utc(), $now->copy()->endOfDay()->utc()],
            DatePeriod::YESTERDAY => [$now->copy()->subDay()->startOfDay()->utc(), $now->copy()->subDay()->endOfDay()->utc()],
            DatePeriod::THIS_WEEK => [$now->copy()->startOfWeek()->utc(), $now->copy()->endOfWeek()->utc()],
            DatePeriod::THIS_MONTH => [$now->copy()->startOfMonth()->utc(), $now->copy()->endOfMonth()->utc()],
            DatePeriod::LAST_MONTH => [$now->copy()->subMonth()->startOfMonth()->utc(), $now->copy()->subMonth()->endOfMonth()->utc()],
            DatePeriod::CUSTOM => [
                Carbon::parse($this->customStartDate, 'Asia/Jakarta')->startOfDay()->utc(),
                Carbon::parse($this->customEndDate, 'Asia/Jakarta')->endOfDay()->utc(),
            ],
            default => [$now->copy()->startOfMonth()->utc(), $now->copy()->endOfMonth()->utc()],
        };
    }

    public function render()
    {
        [$start, $end] = $this->getDateRange();
        $rows = app(ReportService::class)->getCustomerNominal($start, $end, $this->search ?: null);

        return view('livewire.reports.customer-nominal-report', [
            'rows' => $rows,
            'startDate' => $start,
            'endDate' => $end,
            'grandTotal' => $rows->sum('total_spent'),
            'invoiceCount' => $rows->sum('invoice_count'),
        ]);
    }
}
