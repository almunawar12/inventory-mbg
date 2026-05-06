<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-4">
    @include('livewire.reports.partials.filter-bar', [
        'title' => 'Product Report',
        'subtitle' => 'Ringkasan penjualan per produk.',
    ])

    <div class="grid gap-4 md:grid-cols-3">
        <div class="rounded-xl border bg-green-200 bg-card text-card-foreground shadow-sm p-4">
            <h3 class="text-sm font-medium">Total Qty Terjual</h3>
            <div class="text-xl sm:text-2xl font-bold mt-1">{{ number_format($totalQty) }}</div>
        </div>
        <div class="rounded-xl border bg-yellow-200 bg-card text-card-foreground shadow-sm p-4">
            <h3 class="text-sm font-medium">Total Revenue</h3>
            <div class="text-xl sm:text-2xl font-bold mt-1">@money($totalRevenue)</div>
        </div>
        <div class="rounded-xl border bg-purple-200 bg-card text-card-foreground shadow-sm p-4">
            <h3 class="text-sm font-medium">Total Profit</h3>
            <div class="text-xl sm:text-2xl font-bold mt-1 {{ $totalProfit >= 0 ? 'text-emerald-700' : 'text-red-700' }}">
                @money($totalProfit)
            </div>
        </div>
    </div>

    <div class="rounded-xl border bg-card shadow-sm overflow-hidden">
        <div class="p-4 border-b bg-muted/40">
            <h3 class="text-sm font-semibold">
                Periode: {{ \Carbon\Carbon::parse($startDate)->setTimezone('Asia/Jakarta')->format('d M Y') }} —
                {{ \Carbon\Carbon::parse($endDate)->setTimezone('Asia/Jakarta')->format('d M Y') }}
            </h3>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-muted/50 text-left">
                    <tr>
                        <th class="px-4 py-2 font-semibold">#</th>
                        <th class="px-4 py-2 font-semibold">SKU</th>
                        <th class="px-4 py-2 font-semibold">Produk</th>
                        <th class="px-4 py-2 font-semibold text-right">Qty Terjual</th>
                        <th class="px-4 py-2 font-semibold text-right">Stok Saat Ini</th>
                        <th class="px-4 py-2 font-semibold text-right">Revenue</th>
                        <th class="px-4 py-2 font-semibold text-right">Profit</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $i => $row)
                        <tr class="border-t hover:bg-muted/30">
                            <td class="px-4 py-2">{{ $i + 1 }}</td>
                            <td class="px-4 py-2 font-mono text-xs">{{ $row->sku }}</td>
                            <td class="px-4 py-2 font-medium">{{ $row->product_name }}</td>
                            <td class="px-4 py-2 text-right">{{ number_format($row->total_qty) }}</td>
                            <td class="px-4 py-2 text-right {{ $row->current_stock <= 0 ? 'text-red-600 font-semibold' : '' }}">
                                {{ number_format($row->current_stock) }}
                            </td>
                            <td class="px-4 py-2 text-right font-semibold">@money($row->total_revenue)</td>
                            <td class="px-4 py-2 text-right {{ $row->total_profit >= 0 ? 'text-emerald-700' : 'text-red-700' }}">
                                @money($row->total_profit)
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-8 text-center text-muted-foreground">
                                Tidak ada penjualan pada periode ini.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
