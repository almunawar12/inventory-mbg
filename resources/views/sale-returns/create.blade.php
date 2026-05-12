@php
    $alreadyReturnedMap = [];
    $initialSale = null;
    $initialItems = [];
    if ($sale) {
        $alreadyReturnedMap = \App\Models\SaleReturnItem::query()
            ->whereIn('sale_item_id', $sale->items->pluck('id'))
            ->selectRaw('sale_item_id, SUM(quantity) as total')
            ->groupBy('sale_item_id')
            ->pluck('total', 'sale_item_id')
            ->toArray();

        $initialSale = [
            'id' => $sale->id,
            'invoice_number' => $sale->invoice_number,
            'sale_date' => optional($sale->sale_date)->format('d/m/Y H:i'),
            'customer' => $sale->customer?->name ?? 'Guest',
            'total' => $sale->total,
        ];

        $initialItems = $sale->items->map(function ($item) use ($alreadyReturnedMap) {
            $alreadyReturned = (int) ($alreadyReturnedMap[$item->id] ?? 0);
            return [
                'sale_item_id'     => $item->id,
                'product_name'     => $item->product?->name,
                'unit'             => $item->product?->unit?->name,
                'quantity'         => $item->quantity,
                'already_returned' => $alreadyReturned,
                'available_qty'    => $item->quantity - $alreadyReturned,
                'final_price'      => $item->final_price,
                'return_qty'       => 0,
            ];
        })->values()->toArray();
    }
@endphp
<x-app-layout title="New Return">
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-foreground leading-tight">{{ __('New Return') }}</h2>
    </x-slot>

    <div class="py-4">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8" x-data="saleReturnForm()">

            <template x-if="!sale">
                <div class="bg-white shadow rounded p-4 space-y-3">
                    <label class="block">
                        <span class="text-sm font-medium">Invoice Number</span>
                        <input type="text" x-model="invoice_input" class="mt-1 w-full border rounded px-3 py-2" placeholder="e.g. INV.260511.0001">
                    </label>
                    <div class="text-red-600 text-sm" x-show="lookup_error" x-text="lookup_error"></div>
                    <button type="button" @click="lookup()" class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded">Cari</button>
                </div>
            </template>

            <template x-if="sale">
                <form method="POST" action="{{ route('sale-returns.store') }}" class="space-y-4">
                    @csrf
                    <input type="hidden" name="sale_id" :value="sale.id">

                    <div class="bg-white shadow rounded p-4">
                        <div class="grid grid-cols-2 gap-4 text-sm">
                            <div><span class="font-semibold">Invoice:</span> <span x-text="sale.invoice_number"></span></div>
                            <div><span class="font-semibold">Customer:</span> <span x-text="sale.customer"></span></div>
                            <div><span class="font-semibold">Date:</span> <span x-text="sale.sale_date"></span></div>
                            <div><span class="font-semibold">Total:</span> <span x-text="formatMoney(sale.total)"></span></div>
                        </div>
                    </div>

                    <div class="bg-white shadow rounded p-4 overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead class="bg-gray-100 text-left">
                                <tr>
                                    <th class="p-2">Product</th>
                                    <th class="p-2 text-right">Sold</th>
                                    <th class="p-2 text-right">Already Returned</th>
                                    <th class="p-2 text-right">Return Qty</th>
                                    <th class="p-2 text-right">Unit Price</th>
                                    <th class="p-2 text-right">Refund</th>
                                </tr>
                            </thead>
                            <tbody>
                                <template x-for="(item, idx) in items" :key="item.sale_item_id">
                                    <tr class="border-t">
                                        <td class="p-2">
                                            <div x-text="item.product_name"></div>
                                            <div class="text-xs text-gray-500" x-text="item.unit"></div>
                                            <template x-if="item.return_qty > 0">
                                                <div>
                                                    <input type="hidden" :name="`items[${idx}][sale_item_id]`" :value="item.sale_item_id">
                                                    <input type="hidden" :name="`items[${idx}][quantity]`" :value="item.return_qty">
                                                </div>
                                            </template>
                                        </td>
                                        <td class="p-2 text-right" x-text="item.quantity"></td>
                                        <td class="p-2 text-right" x-text="item.already_returned"></td>
                                        <td class="p-2 text-right">
                                            <input type="number" min="0" :max="item.available_qty"
                                                :disabled="item.available_qty === 0"
                                                x-model.number="item.return_qty"
                                                class="w-20 border rounded px-2 py-1 text-right">
                                        </td>
                                        <td class="p-2 text-right" x-text="formatMoney(item.final_price)"></td>
                                        <td class="p-2 text-right" x-text="formatMoney(itemRefund(item))"></td>
                                    </tr>
                                </template>
                            </tbody>
                            <tfoot>
                                <tr class="border-t font-semibold">
                                    <td class="p-2 text-right" colspan="5">Total Refund</td>
                                    <td class="p-2 text-right" x-text="formatMoney(totalRefund())"></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>

                    <div class="bg-white shadow rounded p-4 grid grid-cols-1 md:grid-cols-2 gap-4">
                        <label class="block">
                            <span class="text-sm font-medium">Reason</span>
                            <textarea name="reason" x-model="reason" rows="3" class="mt-1 w-full border rounded px-3 py-2"></textarea>
                        </label>
                        <label class="block">
                            <span class="text-sm font-medium">Notes</span>
                            <textarea name="notes" x-model="notes" rows="3" class="mt-1 w-full border rounded px-3 py-2"></textarea>
                        </label>
                    </div>

                    <div class="flex justify-end space-x-2">
                        <a href="{{ route('sale-returns.index') }}" class="bg-gray-300 hover:bg-gray-400 text-gray-800 px-4 py-2 rounded">Cancel</a>
                        <button type="submit" :disabled="totalRefund() === 0"
                            class="bg-amber-500 hover:bg-amber-600 disabled:opacity-50 text-white px-4 py-2 rounded">
                            Save Return
                        </button>
                    </div>
                </form>
            </template>

            @if(session('error'))
                <div class="mt-3 p-3 bg-red-100 text-red-700 rounded">{{ session('error') }}</div>
            @endif

        </div>
    </div>

    <script>
        function saleReturnForm() {
            return {
                sale: @json($initialSale),
                items: @json($initialItems),
                invoice_input: '',
                lookup_error: '',
                reason: '',
                notes: '',
                async lookup() {
                    this.lookup_error = '';
                    try {
                        const res = await fetch('{{ route('ajax.sales.lookup') }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                                'Accept': 'application/json',
                            },
                            body: JSON.stringify({ invoice_number: this.invoice_input }),
                        });
                        if (!res.ok) {
                            const data = await res.json().catch(() => ({}));
                            this.lookup_error = data.message || 'Invoice not found.';
                            return;
                        }
                        const data = await res.json();
                        this.sale = data.sale;
                        this.items = data.items.map(i => ({ ...i, return_qty: 0 }));
                    } catch (e) {
                        this.lookup_error = 'Lookup failed: ' + e.message;
                    }
                },
                itemRefund(item) {
                    return item.return_qty * item.final_price;
                },
                totalRefund() {
                    return this.items.reduce((s, i) => s + this.itemRefund(i), 0);
                },
                formatMoney(n) {
                    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(n);
                }
            }
        }
    </script>
</x-app-layout>
