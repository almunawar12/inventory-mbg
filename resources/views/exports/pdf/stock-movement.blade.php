<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Riwayat Stok</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #1f2937; }
        h1 { margin: 0 0 4px 0; font-size: 16px; }
        .meta { color: #6b7280; margin-bottom: 12px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #e5e7eb; padding: 4px 6px; }
        th { background: #e0f2fe; text-align: left; }
        .num { text-align: right; }
        .in { color: #047857; font-weight: 600; }
        .out { color: #b91c1c; font-weight: 600; }
        .totals { margin-top: 12px; }
    </style>
</head>
<body>
    <h1>Riwayat Stok</h1>
    <div class="meta">
        Periode: {{ $start->copy()->setTimezone('Asia/Jakarta')->format('d M Y') }}
        &mdash;
        {{ $end->copy()->setTimezone('Asia/Jakarta')->format('d M Y') }}
    </div>

    <table>
        <thead>
            <tr>
                <th>Tanggal</th>
                <th>Jenis</th>
                <th>Sumber</th>
                <th>No. Ref</th>
                <th>SKU</th>
                <th>Produk</th>
                <th class="num">Qty</th>
                <th class="num">Harga</th>
                <th class="num">Total</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr>
                    <td>{{ \Carbon\Carbon::parse($row->occurred_at)->setTimezone('Asia/Jakarta')->format('d M Y H:i') }}</td>
                    <td class="{{ $row->type === 'IN' ? 'in' : 'out' }}">{{ $row->type === 'IN' ? 'Masuk' : 'Keluar' }}</td>
                    <td>{{ ucfirst($row->source) }}</td>
                    <td>{{ $row->reference_no }}</td>
                    <td>{{ $row->sku }}</td>
                    <td>{{ $row->product_name }}</td>
                    <td class="num">{{ number_format($row->qty) }}</td>
                    <td class="num">{{ number_format((int) $row->unit_price) }}</td>
                    <td class="num">{{ number_format((int) $row->line_total) }}</td>
                </tr>
            @empty
                <tr><td colspan="9" style="text-align:center;padding:16px;">Tidak ada data.</td></tr>
            @endforelse
        </tbody>
    </table>

    <div class="totals">
        Total Masuk: <strong>{{ number_format($totals['in']) }}</strong> &nbsp;|&nbsp;
        Total Keluar: <strong>{{ number_format($totals['out']) }}</strong> &nbsp;|&nbsp;
        Net: <strong>{{ number_format($totals['net']) }}</strong>
    </div>
</body>
</html>
