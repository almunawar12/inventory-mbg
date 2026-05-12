<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Kartu Stok {{ $product->name }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #1f2937; }
        h1 { margin: 0 0 4px 0; font-size: 16px; }
        .meta { color: #6b7280; margin-bottom: 12px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #e5e7eb; padding: 4px 6px; }
        th { background: #e0f2fe; text-align: left; }
        .num { text-align: right; }
        .saldo { background: #f0f9ff; font-weight: 600; }
        .in { color: #047857; }
        .out { color: #b91c1c; }
    </style>
</head>
<body>
    <h1>Kartu Stok &mdash; {{ $product->name }}</h1>
    <div class="meta">
        SKU: {{ $product->sku }} &nbsp;|&nbsp;
        Stok saat ini: {{ number_format($product->quantity) }} &nbsp;|&nbsp;
        Periode: {{ $start->copy()->setTimezone('Asia/Jakarta')->format('d M Y') }} &mdash;
        {{ $end->copy()->setTimezone('Asia/Jakarta')->format('d M Y') }}
    </div>

    <table>
        <thead>
            <tr>
                <th>Tanggal</th>
                <th>Jenis</th>
                <th>Sumber</th>
                <th>No. Ref</th>
                <th class="num">Qty</th>
                <th class="num">Saldo Berjalan</th>
            </tr>
        </thead>
        <tbody>
            <tr class="saldo"><td colspan="5">Saldo Awal</td><td class="num">{{ number_format($ledger['opening']) }}</td></tr>
            @forelse ($ledger['rows'] as $row)
                <tr>
                    <td>{{ \Carbon\Carbon::parse($row->occurred_at)->setTimezone('Asia/Jakarta')->format('d M Y H:i') }}</td>
                    <td class="{{ $row->type === 'IN' ? 'in' : 'out' }}">{{ $row->type === 'IN' ? 'Masuk' : 'Keluar' }}</td>
                    <td>{{ ucfirst($row->source) }}</td>
                    <td>{{ $row->reference_no }}</td>
                    <td class="num {{ $row->type === 'IN' ? 'in' : 'out' }}">
                        {{ ($row->type === 'IN' ? '+' : '-') . number_format($row->qty) }}
                    </td>
                    <td class="num">{{ number_format($row->running_balance) }}</td>
                </tr>
            @empty
                <tr><td colspan="6" style="text-align:center;padding:16px;">Tidak ada pergerakan.</td></tr>
            @endforelse
            <tr class="saldo"><td colspan="5">Saldo Akhir</td><td class="num">{{ number_format($ledger['closing']) }}</td></tr>
        </tbody>
    </table>
</body>
</html>
