<?php

namespace App\Http\Controllers\Api;

use App\Models\Product;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Cache;
use App\Imports\ProductImport;
use App\Exports\ProductTemplateExport;
use Maatwebsite\Excel\Facades\Excel;

class ProductController extends Controller
{
    public function showImportForm()
    {
        return view('import');
    }

    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|mimes:xls,xlsx|max:5120',
        ]);

        $import = new ProductImport();
        Excel::import($import, $request->file('file'));

        $msg = "Berhasil import {$import->imported} produk.";

        // Filter out errors that are just "The sku field is required." for empty rows
        $actualFailures = $import->failures()->filter(function ($failure) {
            $values = array_filter($failure->values());
            return !empty($values);
        });

        $failureCount = $actualFailures->count();
        $errorCount = count($import->errors());

        // Only count real validation failures
        $totalFailed = $failureCount + $errorCount;

        if ($totalFailed > 0 || $import->skippedFk > 0) {
            $failedInfo = [];
            if ($totalFailed > 0) $failedInfo[] = "{$totalFailed} baris gagal validasi";
            if ($import->skippedFk > 0) $failedInfo[] = "{$import->skippedFk} data kategori/unit tidak ditemukan";

            $msg .= " " . implode(' dan ', $failedInfo) . ".";

            return redirect()->back()
                ->with('warning', $msg)
                ->with('failures', $actualFailures);
        }

        return redirect()->back()->with('success', $msg);
    }

    public function downloadTemplate()
    {
        return Excel::download(new ProductTemplateExport(), 'product_import_template.xlsx');
    }

    public function search(Request $request)
    {
        $query = $request->input('q') ?? $request->input('search');

        $cacheKey = 'products_search_' . md5($query);

        $products = Cache::remember($cacheKey, 300, function () use ($query) {
            return Product::query()
                ->with(['unit'])
                ->where('quantity', '>', 0) // Only show available products
                ->when($query, function ($q) use ($query) {
                    $q->where('name', 'like', "%{$query}%")
                        ->orWhere('sku', 'like', "%{$query}%");
                })
                ->limit(50)
                ->get()
                ->map(function ($product) {
                    return [
                        'value' => $product->id,
                        'id' => $product->id,
                        'text' => $product->name,
                        'name' => $product->name,
                        'price' => $product->purchase_price,
                        'selling_price' => $product->selling_price,
                        'sku' => $product->sku,
                        'quantity' => $product->quantity,
                        'unit' => $product->unit ? [
                            'symbol' => $product->unit->symbol,
                            'name' => $product->unit->name
                        ] : null,
                    ];
                });
        });

        return response()->json($products);
    }
}
