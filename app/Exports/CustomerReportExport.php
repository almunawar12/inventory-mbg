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

class CustomerReportExport implements FromCollection, WithHeadings, WithMapping, WithTitle, ShouldAutoSize, WithEvents
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
        return [
            'Customer',
            'Phone',
            'Invoice Count',
            'Total Qty',
            'Total Nominal',
            'Last Purchase',
        ];
    }

    public function map($row): array
    {
        return [
            $row->customer_name,
            $row->customer_phone,
            (int) $row->invoice_count,
            (int) $row->total_qty,
            (int) $row->total_nominal,
            $row->last_purchase ? Carbon::parse($row->last_purchase)->setTimezone('Asia/Jakarta')->format('Y-m-d H:i') : '',
        ];
    }

    public function title(): string
    {
        return 'Customer Report';
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
