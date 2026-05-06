<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Customer Nominal</title>
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
    </style>
</head>
<body>
    <h1>Customer Nominal Report</h1>
    <div class="meta">
        Periode: {{ \Carbon\Carbon::parse($start)->setTimezone('Asia/Jakarta')->format('d M Y') }}
        — {{ \Carbon\Carbon::parse($end)->setTimezone('Asia/Jakarta')->format('d M Y') }}<br>
        Generated: {{ now()->setTimezone('Asia/Jakarta')->format('d M Y H:i') }}
    </div>

    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Customer</th>
                <th>Phone</th>
                <th class="text-right">Invoice</th>
                <th class="text-right">Total Spent</th>
                <th class="text-right">Kontribusi</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $i => $row)
                <tr>
                    <td>{{ $i + 1 }}</td>
                    <td>{{ $row->customer_name }}</td>
                    <td>{{ $row->customer_phone }}</td>
                    <td class="text-right">{{ number_format($row->invoice_count) }}</td>
                    <td class="text-right">{{ format_money($row->total_spent) }}</td>
                    <td class="text-right">{{ number_format($row->percentage, 2) }}%</td>
                </tr>
            @empty
                <tr><td colspan="6" style="text-align:center;padding:16px;">Tidak ada transaksi.</td></tr>
            @endforelse
        </tbody>
        @if ($rows->count())
            <tfoot>
                <tr>
                    <td colspan="3">GRAND TOTAL</td>
                    <td class="text-right">{{ number_format($rows->sum('invoice_count')) }}</td>
                    <td class="text-right">{{ format_money($rows->sum('total_spent')) }}</td>
                    <td class="text-right">100%</td>
                </tr>
            </tfoot>
        @endif
    </table>
</body>
</html>
