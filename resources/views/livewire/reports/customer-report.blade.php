<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-4">
    @include('livewire.reports.partials.filter-bar', [
        'title' => 'Customer Report',
        'subtitle' => 'Ringkasan transaksi per customer.',
    ])

    <div class="grid gap-4 md:grid-cols-3">
        <div class="rounded-xl border bg-green-200 bg-card text-card-foreground shadow-sm p-4">
            <h3 class="text-sm font-medium">Total Nominal</h3>
            <div class="text-xl sm:text-2xl font-bold mt-1">@money($totalNominal)</div>
        </div>
        <div class="rounded-xl border bg-yellow-200 bg-card text-card-foreground shadow-sm p-4">
            <h3 class="text-sm font-medium">Total Invoice</h3>
            <div class="text-xl sm:text-2xl font-bold mt-1">{{ number_format($totalInvoices) }}</div>
        </div>
        <div class="rounded-xl border bg-purple-200 bg-card text-card-foreground shadow-sm p-4">
            <h3 class="text-sm font-medium">Total Qty Item</h3>
            <div class="text-xl sm:text-2xl font-bold mt-1">{{ number_format($totalQty) }}</div>
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
                        <th class="px-4 py-2 font-semibold">Customer</th>
                        <th class="px-4 py-2 font-semibold">Phone</th>
                        <th class="px-4 py-2 font-semibold text-right">Invoice</th>
                        <th class="px-4 py-2 font-semibold text-right">Qty</th>
                        <th class="px-4 py-2 font-semibold text-right">Nominal</th>
                        <th class="px-4 py-2 font-semibold">Last Purchase</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $i => $row)
                        <tr class="border-t hover:bg-muted/30">
                            <td class="px-4 py-2">{{ $i + 1 }}</td>
                            <td class="px-4 py-2 font-medium">{{ $row->customer_name }}</td>
                            <td class="px-4 py-2 text-muted-foreground">{{ $row->customer_phone }}</td>
                            <td class="px-4 py-2 text-right">{{ number_format($row->invoice_count) }}</td>
                            <td class="px-4 py-2 text-right">{{ number_format($row->total_qty) }}</td>
                            <td class="px-4 py-2 text-right font-semibold">@money($row->total_nominal)</td>
                            <td class="px-4 py-2 text-muted-foreground">
                                {{ $row->last_purchase ? \Carbon\Carbon::parse($row->last_purchase)->setTimezone('Asia/Jakarta')->format('d M Y H:i') : '-' }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-8 text-center text-muted-foreground">
                                Tidak ada transaksi pada periode ini.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
