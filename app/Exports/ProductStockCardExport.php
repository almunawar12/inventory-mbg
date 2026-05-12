<?php

namespace App\Exports;

use Carbon\Carbon;
use App\Models\Product;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class ProductStockCardExport implements FromCollection, WithHeadings, WithTitle, ShouldAutoSize, WithEvents
{
    use Exportable;

    /**
     * @param array{opening:int,rows:Collection,closing:int} $ledger
     */
    public function __construct(
        protected Product $product,
        protected array $ledger,
        protected Carbon $start,
        protected Carbon $end,
    ) {}

    public function collection(): Collection
    {
        $out = collect([
            ['', '', '', 'Saldo Awal', '', $this->ledger['opening']],
        ]);

        foreach ($this->ledger['rows'] as $row) {
            $out->push([
                Carbon::parse($row->occurred_at)->setTimezone('Asia/Jakarta')->format('Y-m-d H:i'),
                $row->type === 'IN' ? 'Masuk' : 'Keluar',
                ucfirst($row->source),
                $row->reference_no,
                ($row->type === 'IN' ? '+' : '-') . (int) $row->qty,
                (int) $row->running_balance,
            ]);
        }

        $out->push(['', '', '', 'Saldo Akhir', '', $this->ledger['closing']]);

        return $out;
    }

    public function headings(): array
    {
        return ['Tanggal', 'Jenis', 'Sumber', 'No. Ref', 'Qty', 'Saldo Berjalan'];
    }

    public function title(): string
    {
        return 'Kartu Stok ' . $this->product->sku;
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $sheet->getStyle('A1:F1')->getFont()->setBold(true);
                $sheet->getStyle('A1:F1')->getFill()
                    ->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()->setRGB('E0F2FE');
                $sheet->freezePane('A2');
            },
        ];
    }
}
