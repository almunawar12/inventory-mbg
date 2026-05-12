<?php

namespace App\Exports;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class StockMovementExport implements FromCollection, WithHeadings, WithMapping, WithTitle, ShouldAutoSize, WithEvents
{
    use Exportable;

    public function __construct(
        protected Collection $rows,
        protected Carbon $start,
        protected Carbon $end,
    ) {}

    public function collection(): Collection
    {
        return $this->rows;
    }

    public function headings(): array
    {
        return ['Tanggal', 'Jenis', 'Sumber', 'No. Ref', 'SKU', 'Produk', 'Qty', 'Harga', 'Total'];
    }

    public function map($row): array
    {
        return [
            Carbon::parse($row->occurred_at)->setTimezone('Asia/Jakarta')->format('Y-m-d H:i'),
            $row->type === 'IN' ? 'Masuk' : 'Keluar',
            ucfirst($row->source),
            $row->reference_no,
            $row->sku,
            $row->product_name,
            (int) $row->qty,
            (int) $row->unit_price,
            (int) $row->line_total,
        ];
    }

    public function title(): string
    {
        return 'Stock Movement';
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $sheet->getStyle('A1:I1')->getFont()->setBold(true);
                $sheet->getStyle('A1:I1')->getFill()
                    ->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()->setRGB('E0F2FE');
                $sheet->freezePane('A2');
            },
        ];
    }
}
