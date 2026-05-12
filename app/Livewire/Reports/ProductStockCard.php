<?php

namespace App\Livewire\Reports;

use Carbon\Carbon;
use Livewire\Component;
use App\Models\Product;
use App\Enums\DatePeriod;
use App\Services\StockMovementService;
use App\Exports\ProductStockCardExport;
use Maatwebsite\Excel\Facades\Excel;
use Barryvdh\DomPDF\Facade\Pdf;

class ProductStockCard extends Component
{
    public Product $product;
    public string $dateFilter = DatePeriod::THIS_MONTH->value;
    public ?string $customStartDate = null;
    public ?string $customEndDate = null;
    public string $search = ''; // unused; satisfies shared filter-bar partial

    public function mount(Product $product): void
    {
        $this->product = $product;
        $this->customStartDate = Carbon::now('Asia/Jakarta')->startOfMonth()->format('Y-m-d');
        $this->customEndDate   = Carbon::now('Asia/Jakarta')->endOfMonth()->format('Y-m-d');
    }

    public function updateCustomRange(string $start, string $end): void
    {
        $this->customStartDate = $start;
        $this->customEndDate   = $end;
    }

    public function refresh(): void
    {
        // forces re-render
    }

    public function exportExcel()
    {
        [$start, $end] = $this->getDateRange();
        $ledger = app(StockMovementService::class)->productLedger($this->product->id, $start, $end);
        $file = 'kartu-stok-' . $this->product->sku . '-' . $start->format('Ymd') . '-' . $end->format('Ymd') . '.xlsx';

        return Excel::download(
            new ProductStockCardExport($this->product, $ledger, $start, $end),
            $file
        );
    }

    public function exportPdf()
    {
        [$start, $end] = $this->getDateRange();
        $ledger = app(StockMovementService::class)->productLedger($this->product->id, $start, $end);

        $pdf = Pdf::loadView('exports.pdf.product-stock-card', [
            'product' => $this->product,
            'ledger'  => $ledger,
            'start'   => $start,
            'end'     => $end,
        ])->setPaper('a4', 'landscape');

        return response()->streamDownload(
            fn () => print($pdf->output()),
            'kartu-stok-' . $this->product->sku . '-' . $start->format('Ymd') . '-' . $end->format('Ymd') . '.pdf'
        );
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
        $ledger = app(StockMovementService::class)->productLedger($this->product->id, $start, $end);

        return view('livewire.reports.product-stock-card', [
            'product'   => $this->product,
            'ledger'    => $ledger,
            'startDate' => $start,
            'endDate'   => $end,
        ]);
    }
}
