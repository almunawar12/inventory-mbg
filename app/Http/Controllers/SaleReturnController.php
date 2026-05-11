<?php

namespace App\Http\Controllers;

use App\Models\Sale;
use App\Models\SaleReturn;
use App\DTOs\SaleReturnData;
use App\Services\SaleReturnService;
use App\Exceptions\SaleReturnException;
use App\Http\Requests\StoreSaleReturnRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SaleReturnController extends Controller
{
    public function index()
    {
        return view('sale-returns.index');
    }

    public function create(Request $request)
    {
        $sale = null;
        if ($request->filled('sale_id')) {
            $sale = Sale::with(['items.product.unit', 'customer'])->findOrFail($request->integer('sale_id'));
        }
        return view('sale-returns.create', compact('sale'));
    }

    public function store(StoreSaleReturnRequest $request, SaleReturnService $service)
    {
        try {
            $validated = $request->validated();
            $validated['created_by'] = Auth::id();

            $return = $service->createReturn(SaleReturnData::fromArray($validated));

            if ($request->wantsJson()) {
                return response()->json([
                    'success'   => true,
                    'message'   => 'Return created successfully',
                    'data'      => $return,
                    'print_url' => route('sale-returns.print', $return->id),
                    'redirect'  => route('sale-returns.show', $return->id),
                ], 201);
            }

            return redirect()->route('sale-returns.show', $return->id)
                ->with('success', 'Return created successfully.');

        } catch (SaleReturnException $e) {
            if ($request->wantsJson()) {
                return response()->json(['message' => $e->getMessage()], 400);
            }
            return back()->with('error', $e->getMessage())->withInput();
        } catch (\Exception $e) {
            if ($request->wantsJson()) {
                return response()->json(['message' => $e->getMessage()], 400);
            }
            return back()->with('error', $e->getMessage())->withInput();
        }
    }

    public function show(SaleReturn $saleReturn)
    {
        $saleReturn->load(['items.product.unit', 'sale.customer', 'creator']);
        return view('sale-returns.show', ['return' => $saleReturn]);
    }

    public function destroy(SaleReturn $saleReturn, SaleReturnService $service)
    {
        try {
            $service->deleteReturn($saleReturn);
            return redirect()->route('sale-returns.index')->with('success', 'Return deleted successfully.');
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function print(SaleReturn $saleReturn)
    {
        $saleReturn->load(['items.product.unit', 'sale.customer', 'creator']);
        return view('sale-returns.print', ['return' => $saleReturn]);
    }
}
