<?php

namespace App\Livewire\Reports;

use Carbon\Carbon;
use Livewire\Component;
use Livewire\WithPagination;
use App\Enums\DatePeriod;
use App\Services\StockMovementService;
use App\Exports\StockMovementExport;
use Maatwebsite\Excel\Facades\Excel;
use Barryvdh\DomPDF\Facade\Pdf;

class StockMovementReport extends Component
{
    use WithPagination;

    public string $dateFilter = DatePeriod::THIS_MONTH->value;
    public ?string $customStartDate = null;
    public ?string $customEndDate = null;
    public string $search = '';
    public string $type = 'all';
    public string $source = 'all';

    protected $paginationTheme = 'tailwind';

    public function mount(): void
    {
        $this->customStartDate = Carbon::now('Asia/Jakarta')->startOfMonth()->format('Y-m-d');
        $this->customEndDate   = Carbon::now('Asia/Jakarta')->endOfMonth()->format('Y-m-d');
    }

    public function updatingDateFilter(): void { $this->resetPage(); }
    public function updatingType(): void      { $this->resetPage(); }
    public function updatingSource(): void    { $this->resetPage(); }
    public function updatingSearch(): void    { $this->resetPage(); }

    public function updateCustomRange(string $start, string $end): void
    {
        $this->customStartDate = $start;
        $this->customEndDate   = $end;
        $this->resetPage();
    }

    public function refresh(): void
    {
        $this->resetPage();
    }

    public function exportExcel()
    {
        [$start, $end] = $this->getDateRange();
        $rows = app(StockMovementService::class)->collect($this->filters($start, $end));
        $file = 'stock-movement-' . $start->format('Ymd') . '-' . $end->format('Ymd') . '.xlsx';

        return Excel::download(new StockMovementExport($rows, $start, $end), $file);
    }

    public function exportPdf()
    {
        [$start, $end] = $this->getDateRange();
        $svc    = app(StockMovementService::class);
        $rows   = $svc->collect($this->filters($start, $end));
        $totals = $svc->totals($this->filters($start, $end));

        $pdf = Pdf::loadView('exports.pdf.stock-movement', compact('rows', 'totals', 'start', 'end'))
            ->setPaper('a4', 'landscape');

        return response()->streamDownload(
            fn () => print($pdf->output()),
            'stock-movement-' . $start->format('Ymd') . '-' . $end->format('Ymd') . '.pdf'
        );
    }

    protected function filters(Carbon $start, Carbon $end): array
    {
        return [
            'start'          => $start,
            'end'            => $end,
            'type'           => $this->type,
            'source'         => $this->source,
            'product_search' => $this->search ?: null,
        ];
    }

    protected function getDateRange(): array
    {
        $now = Carbon::now('Asia/Jakarta');

        return match (DatePeriod::tryFrom($this->dateFilter)) {
            DatePeriod::TODAY      => [$now->copy()->startOfDay()->utc(), $now->copy()->endOfDay()->utc()],
            DatePeriod::YESTERDAY  => [$now->copy()->subDay()->startOfDay()->utc(), $now->copy()->subDay()->endOfDay()->utc()],
            DatePeriod::THIS_WEEK  => [$now->copy()->startOfWeek()->utc(), $now->copy()->endOfWeek()->utc()],
            DatePeriod::THIS_MONTH => [$now->copy()->startOfMonth()->utc(), $now->copy()->endOfMonth()->utc()],
            DatePeriod::LAST_MONTH => [$now->copy()->subMonth()->startOfMonth()->utc(), $now->copy()->subMonth()->endOfMonth()->utc()],
            DatePeriod::CUSTOM     => [
                Carbon::parse($this->customStartDate, 'Asia/Jakarta')->startOfDay()->utc(),
                Carbon::parse($this->customEndDate,   'Asia/Jakarta')->endOfDay()->utc(),
            ],
            default                => [$now->copy()->startOfMonth()->utc(), $now->copy()->endOfMonth()->utc()],
        };
    }

    public function render()
    {
        [$start, $end] = $this->getDateRange();
        $svc     = app(StockMovementService::class);
        $filters = $this->filters($start, $end);

        $rows   = $svc->paginate($filters, 25);
        $totals = $svc->totals($filters);

        return view('livewire.reports.stock-movement-report', [
            'rows'      => $rows,
            'totals'    => $totals,
            'startDate' => $start,
            'endDate'   => $end,
        ]);
    }
}
