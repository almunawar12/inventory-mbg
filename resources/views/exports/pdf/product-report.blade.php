<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Product Report</title>
    <style>
        * { font-family: DejaVu Sans, sans-serif; }
        body { font-size: 11px; color: #111; margin: 0; padding: 16px; }
        h1 { font-size: 16px; margin: 0 0 4px 0; }
        .meta { font-size: 10px; color: #555; margin-bottom: 12px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #888; padding: 5px 6px; }
        th { background: #e0f2fe; text-align: left; }
        .text-right { text-align: right; }
        tfoot td { font-weight: bold; background: #f5f5f5; }
        .neg { color: #b91c1c; }
    </style>
</head>
<body>
    <h1>Product Report</h1>
    <div class="meta">
        Periode: {{ \Carbon\Carbon::parse($start)->setTimezone('Asia/Jakarta')->format('d M Y') }}
        — {{ \Carbon\Carbon::parse($end)->setTimezone('Asia/Jakarta')->format('d M Y') }}<br>
        Generated: {{ now()->setTimezone('Asia/Jakarta')->format('d M Y H:i') }}
    </div>

    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>SKU</th>
                <th>Produk</th>
                <th class="text-right">Qty Terjual</th>
                <th class="text-right">Stok</th>
                <th class="text-right">Revenue</th>
                <th class="text-right">Profit</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $i => $row)
                <tr>
                    <td>{{ $i + 1 }}</td>
                    <td>{{ $row->sku }}</td>
                    <td>{{ $row->product_name }}</td>
                    <td class="text-right">{{ number_format($row->total_qty) }}</td>
                    <td class="text-right">{{ number_format($row->current_stock) }}</td>
                    <td class="text-right">{{ format_money($row->total_revenue) }}</td>
                    <td class="text-right {{ $row->total_profit < 0 ? 'neg' : '' }}">{{ format_money($row->total_profit) }}</td>
                </tr>
            @empty
                <tr><td colspan="7" style="text-align:center;padding:16px;">Tidak ada penjualan.</td></tr>
            @endforelse
        </tbody>
        @if ($rows->count())
            <tfoot>
                <tr>
                    <td colspan="3">TOTAL</td>
                    <td class="text-right">{{ number_format($rows->sum('total_qty')) }}</td>
                    <td></td>
                    <td class="text-right">{{ format_money($rows->sum('total_revenue')) }}</td>
                    <td class="text-right">{{ format_money($rows->sum('total_profit')) }}</td>
                </tr>
            </tfoot>
        @endif
    </table>
</body>
</html>
