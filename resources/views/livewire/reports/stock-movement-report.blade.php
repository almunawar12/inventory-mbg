<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-4">
    @include('livewire.reports.partials.filter-bar', [
        'title' => 'Riwayat Stok',
        'subtitle' => 'Pergerakan barang masuk dan keluar.',
    ])

    <div class="flex flex-wrap gap-2 print:hidden">
        <select wire:model.live="type"
            class="h-9 rounded-md border border-input bg-background px-3 py-1 text-sm shadow-sm">
            <option value="all">Semua Jenis</option>
            <option value="in">Masuk</option>
            <option value="out">Keluar</option>
        </select>
        <select wire:model.live="source"
            class="h-9 rounded-md border border-input bg-background px-3 py-1 text-sm shadow-sm">
            <option value="all">Semua Sumber</option>
            <option value="purchase">Pembelian</option>
            <option value="sale">Penjualan</option>
            <option value="return">Retur</option>
        </select>
    </div>

    <div class="grid gap-4 md:grid-cols-3">
        <div class="rounded-xl border bg-green-200 p-4">
            <h3 class="text-sm font-medium">Total Masuk</h3>
            <div class="text-xl sm:text-2xl font-bold mt-1">{{ number_format($totals['in']) }}</div>
        </div>
        <div class="rounded-xl border bg-red-200 p-4">
            <h3 class="text-sm font-medium">Total Keluar</h3>
            <div class="text-xl sm:text-2xl font-bold mt-1">{{ number_format($totals['out']) }}</div>
        </div>
        <div class="rounded-xl border bg-purple-200 p-4">
            <h3 class="text-sm font-medium">Net</h3>
            <div class="text-xl sm:text-2xl font-bold mt-1 {{ $totals['net'] >= 0 ? 'text-emerald-700' : 'text-red-700' }}">
                {{ number_format($totals['net']) }}
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
                        <th class="px-4 py-2 font-semibold">Tanggal</th>
                        <th class="px-4 py-2 font-semibold">Jenis</th>
                        <th class="px-4 py-2 font-semibold">Sumber</th>
                        <th class="px-4 py-2 font-semibold">No. Ref</th>
                        <th class="px-4 py-2 font-semibold">SKU</th>
                        <th class="px-4 py-2 font-semibold">Produk</th>
                        <th class="px-4 py-2 font-semibold text-right">Qty</th>
                        <th class="px-4 py-2 font-semibold text-right">Harga</th>
                        <th class="px-4 py-2 font-semibold text-right">Total</th>
                        <th class="px-4 py-2 font-semibold"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $row)
                        <tr class="border-t hover:bg-muted/30">
                            <td class="px-4 py-2 whitespace-nowrap">
                                {{ \Carbon\Carbon::parse($row->occurred_at)->setTimezone('Asia/Jakarta')->format('d M Y H:i') }}
                            </td>
                            <td class="px-4 py-2">
                                <span class="px-2 py-0.5 rounded text-xs font-semibold
                                    {{ $row->type === 'IN' ? 'bg-emerald-100 text-emerald-700' : 'bg-red-100 text-red-700' }}">
                                    {{ $row->type === 'IN' ? 'Masuk' : 'Keluar' }}
                                </span>
                            </td>
                            <td class="px-4 py-2 capitalize">{{ $row->source }}</td>
                            <td class="px-4 py-2 font-mono text-xs">{{ $row->reference_no }}</td>
                            <td class="px-4 py-2 font-mono text-xs">{{ $row->sku }}</td>
                            <td class="px-4 py-2 font-medium">{{ $row->product_name }}</td>
                            <td class="px-4 py-2 text-right">{{ number_format($row->qty) }}</td>
                            <td class="px-4 py-2 text-right">@money($row->unit_price)</td>
                            <td class="px-4 py-2 text-right font-semibold">@money($row->line_total)</td>
                            <td class="px-4 py-2">
                                <a href="{{ route('reports.stock-movements.product', $row->product_id) }}"
                                   class="text-sky-600 hover:underline text-xs">Kartu Stok</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10" class="px-4 py-8 text-center text-muted-foreground">
                                Tidak ada pergerakan stok pada periode ini.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="p-4 border-t">
            {{ $rows->links() }}
        </div>
    </div>
</div>
