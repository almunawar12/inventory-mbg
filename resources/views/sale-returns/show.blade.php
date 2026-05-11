<x-app-layout title="Return {{ $return->return_number }}">
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-foreground leading-tight">
                {{ __('Return') }} {{ $return->return_number }}
            </h2>
            <div class="flex space-x-2">
                <a href="{{ route('sale-returns.print', $return->id) }}" target="_blank"
                   class="bg-indigo-500 hover:bg-indigo-600 text-white px-3 py-2 rounded">Print</a>
                <form method="POST" action="{{ route('sale-returns.destroy', $return->id) }}"
                      onsubmit="return confirm('Delete this return? Stock and finance will be reversed.');">
                    @csrf @method('DELETE')
                    <button class="bg-red-500 hover:bg-red-600 text-white px-3 py-2 rounded">Delete</button>
                </form>
                <a href="{{ route('sale-returns.index') }}"
                   class="bg-gray-300 hover:bg-gray-400 text-gray-800 px-3 py-2 rounded">Back</a>
            </div>
        </div>
    </x-slot>

    <div class="py-4">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-4">

            @if(session('error'))
                <div class="p-3 bg-red-100 text-red-700 rounded">{{ session('error') }}</div>
            @endif

            <div class="bg-white shadow rounded p-4 grid grid-cols-1 md:grid-cols-2 gap-2 text-sm">
                <div><span class="font-semibold">Original Invoice:</span>
                    <a href="{{ route('sales.show', $return->sale_id) }}" class="text-blue-600 hover:underline">
                        {{ $return->sale->invoice_number }}
                    </a>
                </div>
                <div><span class="font-semibold">Customer:</span> {{ $return->sale->customer->name ?? 'Guest' }}</div>
                <div><span class="font-semibold">Return Date:</span> {{ $return->return_date->format('d/m/Y H:i') }}</div>
                <div><span class="font-semibold">Created By:</span> {{ $return->creator->name ?? '-' }}</div>
                <div><span class="font-semibold">Total Refund:</span> {{ format_money($return->total_refund) }}</div>
            </div>

            <div class="bg-white shadow rounded p-4 overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-100 text-left">
                        <tr>
                            <th class="p-2">Product</th>
                            <th class="p-2 text-right">Qty</th>
                            <th class="p-2 text-right">Unit Price</th>
                            <th class="p-2 text-right">Subtotal</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($return->items as $item)
                            <tr class="border-t">
                                <td class="p-2">{{ $item->product->name }}
                                    <span class="text-xs text-gray-500">({{ $item->product->unit->name ?? '' }})</span>
                                </td>
                                <td class="p-2 text-right">{{ $item->quantity }}</td>
                                <td class="p-2 text-right">{{ format_money($item->unit_price) }}</td>
                                <td class="p-2 text-right">{{ format_money($item->subtotal) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr class="border-t font-semibold">
                            <td class="p-2 text-right" colspan="3">Total Refund</td>
                            <td class="p-2 text-right">{{ format_money($return->total_refund) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            @if($return->reason || $return->notes)
                <div class="bg-white shadow rounded p-4 space-y-2 text-sm">
                    @if($return->reason)
                        <div><span class="font-semibold">Reason:</span> {{ $return->reason }}</div>
                    @endif
                    @if($return->notes)
                        <div><span class="font-semibold">Notes:</span> {{ $return->notes }}</div>
                    @endif
                </div>
            @endif

        </div>
    </div>
</x-app-layout>
