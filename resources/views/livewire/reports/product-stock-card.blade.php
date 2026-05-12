<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-4">
    @include('livewire.reports.partials.filter-bar', [
        'title' => 'Kartu Stok — ' . $product->name,
        'subtitle' => 'SKU: ' . $product->sku . ' • Stok saat ini: ' . number_format($product->quantity),
        'showSearch' => false,
    ])

    <div class="rounded-xl border bg-card shadow-sm overflow-hidden">
        <div class="p-4 border-b bg-muted/40 flex items-center justify-between">
            <h3 class="text-sm font-semibold">
                Periode: {{ \Carbon\Carbon::parse($startDate)->setTimezone('Asia/Jakarta')->format('d M Y') }} —
                {{ \Carbon\Carbon::parse($endDate)->setTimezone('Asia/Jakarta')->format('d M Y') }}
            </h3>
            <div class="text-sm">
                Saldo Awal: <span class="font-semibold">{{ number_format($ledger['opening']) }}</span> •
                Saldo Akhir: <span class="font-semibold">{{ number_format($ledger['closing']) }}</span>
            </div>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-muted/50 text-left">
                    <tr>
                        <th class="px-4 py-2 font-semibold">Tanggal</th>
                        <th class="px-4 py-2 font-semibold">Jenis</th>
                        <th class="px-4 py-2 font-semibold">Sumber</th>
                        <th class="px-4 py-2 font-semibold">No. Ref</th>
                        <th class="px-4 py-2 font-semibold text-right">Qty</th>
                        <th class="px-4 py-2 font-semibold text-right">Saldo Berjalan</th>
                    </tr>
                </thead>
                <tbody>
                    <tr class="bg-sky-50 border-t">
                        <td colspan="5" class="px-4 py-2 font-semibold">Saldo Awal</td>
                        <td class="px-4 py-2 text-right font-semibold">{{ number_format($ledger['opening']) }}</td>
                    </tr>
                    @forelse ($ledger['rows'] as $row)
                        <tr class="border-t">
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
                            <td class="px-4 py-2 text-right {{ $row->type === 'IN' ? 'text-emerald-700' : 'text-red-700' }}">
                                {{ ($row->type === 'IN' ? '+' : '-') . number_format($row->qty) }}
                            </td>
                            <td class="px-4 py-2 text-right font-semibold">{{ number_format($row->running_balance) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-8 text-center text-muted-foreground">
                                Tidak ada pergerakan pada periode ini.
                            </td>
                        </tr>
                    @endforelse
                    <tr class="bg-sky-50 border-t">
                        <td colspan="5" class="px-4 py-2 font-semibold">Saldo Akhir</td>
                        <td class="px-4 py-2 text-right font-semibold">{{ number_format($ledger['closing']) }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>
