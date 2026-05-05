<?php

namespace App\Exports;

use App\Models\Category;
use App\Models\Unit;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class ProductTemplateSheet implements FromArray, WithHeadings, WithTitle, ShouldAutoSize, WithEvents
{
    use Exportable;

    public function headings(): array
    {
        return [
            'sku', 'name', 'category', 'unit',
            'purchase_price', 'selling_price',
            'quantity', 'min_stock', 'is_active',
            'description', 'notes',
        ];
    }

    public function array(): array
    {
        return [
            [
                'SKU-001', 'Paku Kayu 10cm', 'Hardware', 'Pcs',
                1500, 2000, 100, 10, 1,
                'Paku ukuran 10cm bahan baja', 'Stok promo',
            ],
        ];
    }

    public function title(): string
    {
        return 'Products';
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $sheet->getStyle('A1:K1')->getFont()->setBold(true);
                $sheet->getStyle('A1:K1')->getFill()
                    ->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()->setRGB('E0F2FE');
                $sheet->freezePane('A2');
            },
        ];
    }
}

class ProductReferenceSheet implements FromArray, WithHeadings, WithTitle, ShouldAutoSize
{
    use Exportable;

    public function headings(): array
    {
        return ['Category Names', 'Unit Names'];
    }

    public function array(): array
    {
        $categories = Category::orderBy('name')->pluck('name')->all();
        $units = Unit::orderBy('name')->pluck('name')->all();
        $max = max(count($categories), count($units), 1);
        $rows = [];
        for ($i = 0; $i < $max; $i++) {
            $rows[] = [$categories[$i] ?? '', $units[$i] ?? ''];
        }
        return $rows;
    }

    public function title(): string
    {
        return 'Reference';
    }
}

class ProductTemplateExport implements \Maatwebsite\Excel\Concerns\WithMultipleSheets
{
    use Exportable;

    public function sheets(): array
    {
        return [
            new ProductTemplateSheet(),
            new ProductReferenceSheet(),
        ];
    }
}
