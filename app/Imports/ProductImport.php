<?php

namespace App\Imports;

use App\Models\Category;
use App\Models\Product;
use App\Models\Unit;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\Importable;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\SkipsErrors;
use Maatwebsite\Excel\Concerns\SkipsFailures;
use Maatwebsite\Excel\Concerns\SkipsOnError;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Concerns\WithMapping;

class ProductImport implements
    ToCollection,
    WithHeadingRow,
    WithValidation,
    SkipsOnFailure,
    SkipsOnError,
    SkipsEmptyRows,
    WithChunkReading,
    WithMapping
{
    use Importable, SkipsFailures, SkipsErrors;

    protected array $categoryMap;
    protected array $unitMap;
    public int $imported = 0;
    public int $skippedFk = 0;

    public function __construct()
    {
        $this->categoryMap = Category::query()
            ->select('id', 'name')
            ->get()
            ->map(fn($c) => [
                'id' => $c->id,
                'name' => strtolower(trim($c->name))
            ])
            ->pluck('id', 'name')
            ->all();

        $this->unitMap = Unit::query()
            ->select('id', 'name')
            ->get()
            ->map(fn($u) => [
                'id' => $u->id,
                'name' => strtolower(trim($u->name))
            ])
            ->pluck('id', 'name')
            ->all();
    }

    public function collection(Collection $rows): void
    {
        foreach ($rows as $row) {
            // Very strict empty row check to prevent processing phantom rows
            $sku = trim((string) ($row['sku'] ?? ''));
            $name = trim((string) ($row['name'] ?? ''));

            if ($sku === '' && $name === '') {
                continue;
            }

            $categoryName = strtolower(trim((string) ($row['category'] ?? '')));
            $unitName = strtolower(trim((string) ($row['unit'] ?? '')));

            $categoryId = $this->categoryMap[$categoryName] ?? null;
            $unitId = $this->unitMap[$unitName] ?? null;

            if (!$categoryId || !$unitId) {
                $this->skippedFk++;
                continue;
            }

            Product::create([
                'sku' => trim((string) $row['sku']),
                'name' => trim((string) $row['name']),
                'category_id' => $categoryId,
                'unit_id' => $unitId,
                'purchase_price' => (int) $row['purchase_price'],
                'selling_price' => (int) $row['selling_price'],
                'quantity' => (int) ($row['quantity'] ?? 0),
                'min_stock' => (int) ($row['min_stock'] ?? 0),
                'is_active' => filter_var($row['is_active'] ?? true, FILTER_VALIDATE_BOOLEAN),
                'description' => $row['description'] ?? null,
                'notes' => $row['notes'] ?? null,
            ]);
            $this->imported++;
        }
    }

    public function map($row): array
    {
        // If the row is effectively empty, return an empty array or handle it
        // But with SkipsEmptyRows, we just need to ensure we don't return something that triggers validation for empty rows
        return $row;
    }

    public function rules(): array
    {
        return [
            'sku' => 'required|max:50|unique:products,sku',
            'name' => 'required|max:150',
            'category' => 'required',
            'unit' => 'required',
            'purchase_price' => 'required|numeric|min:0',
            'selling_price' => 'required|numeric|min:0',
            'quantity' => 'nullable|numeric|min:0',
            'min_stock' => 'nullable|numeric|min:0',
        ];
    }

    public function prepareForValidation($data, $index)
    {
        // Many versions of Laravel Excel support prepareForValidation even without a specific interface
        // if it's called from WithValidation. Let's make sure it handles empty rows.
        if (empty(trim((string) ($data['sku'] ?? ''))) && empty(trim((string) ($data['name'] ?? '')))) {
            return []; // Returning empty array usually helps skip validation in newer versions
        }

        return $data;
    }

    public function chunkSize(): int
    {
        return 200;
    }
}
