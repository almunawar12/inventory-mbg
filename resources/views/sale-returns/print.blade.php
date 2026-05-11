<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Return #{{ $return->return_number }}</title>
    <style>
        @media print {
            @page {
                size: A5 landscape;
                margin: 0;
            }
            body {
                margin: 5mm 10mm;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
        }

        body {
            font-family: Arial, sans-serif;
            font-size: 10pt;
            line-height: normal;
            color: #000;
            max-width: 210mm;
            margin: 0 auto;
            background: #fff;
            padding: 10px;
        }

        .container {
            width: 100%;
            border: 0px solid #000;
        }

        /* HEADER GRID */
        .header {
            display: flex;
            width: 100%;
            margin-bottom: 2px;
            border-bottom: 2px solid #000;
            padding-bottom: 5px;
        }

        .header-left {
            width: 60%;
            display: flex;
            align-items: center;
        }

        .logo-box {
            border: 3px double #000;
            width: 60px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24pt;
            font-weight: bold;
            font-family: 'Times New Roman', serif;
            margin-right: 10px;
        }

        .company-info {
            text-align: left;
        }

        .company-name {
            font-family: 'Times New Roman', serif;
            font-size: 16pt;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 2px;
        }

        .company-desc {
            font-size: 8pt;
            margin-bottom: 2px;
        }

        .company-address {
            font-size: 8pt;
        }

        .header-right {
            width: 40%;
            text-align: right;
            padding-left: 20px;
            font-size: 9pt;
        }

        .header-row {
            display: flex;
            margin-bottom: 5px;
            align-items: flex-end;
        }

        .header-right .header-row {
            justify-content: flex-end !important;
        }

        .header-label {
            white-space: nowrap;
            margin-right: 5px;
        }

        .header-value {
            border-bottom: 1px dotted #000;
            flex-grow: 1;
            padding-left: 5px;
        }

        .header-right .header-value {
            flex-grow: 0;
            min-width: 150px;
        }

        /* RETURN NO ROW */
        .invoice-row {
            margin-top: 2px;
            margin-bottom: 5px;
            font-weight: bold;
            font-size: 9pt;
            display: flex;
            align-items: center;
        }

        .invoice-label {
            margin-right: 5px;
            font-style: italic;
        }

        .invoice-value {
            border-bottom: 1px dotted #000;
            min-width: 100px;
            display: inline-block;
        }

        .return-badge {
            margin-left: auto;
            background: #ffeeee;
            border: 2px solid #cc0000;
            color: #cc0000;
            font-size: 10pt;
            font-weight: bold;
            padding: 2px 10px;
            border-radius: 3px;
            letter-spacing: 1px;
        }

        /* TABLE */
        table {
            width: 100%;
            border-collapse: collapse;
            border: 1px solid #000;
            margin-bottom: 5px;
        }

        th {
            border: 1px solid #000;
            padding: 5px;
            text-align: center;
            font-weight: bold;
            background: #f0f0f0;
            font-size: 8pt;
            white-space: nowrap;
        }

        td {
            border-left: 1px solid #000;
            border-right: 1px solid #000;
            border-bottom: 1px solid #000;
            padding: 4px 5px;
            font-size: 8pt;
            vertical-align: middle;
            height: 20px;
        }

        .col-name  { width: 43%; text-align: left; }
        .col-qty   { width: 8%;  text-align: center; }
        .col-price { width: 25%; text-align: right; }
        .col-total { width: 24%; text-align: right; }

        /* FOOTER GRID */
        .footer {
            display: flex;
            margin-top: 5px;
            align-items: flex-start;
        }

        .footer-left {
            width: 25%;
            text-align: center;
            font-size: 9pt;
        }

        .footer-center {
            width: 45%;
            padding: 0 10px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .disclaimer-box {
            border: 1px solid #000;
            border-radius: 5px;
            padding: 8px;
            font-size: 8pt;
            text-align: center;
            background: #f5f5f5;
            width: 100%;
        }

        .footer-right {
            width: 30%;
        }

        .amount-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 5px;
            font-size: 10pt;
            font-weight: bold;
        }

        .amount-label {
            text-align: left;
        }

        .amount-value {
            text-align: right;
            border-bottom: 1px solid #ccc;
            min-width: 80px;
        }

        .signature-space {
            height: 40px;
            margin-top: 5px;
        }

        .reason-box {
            margin-top: 5px;
            font-size: 8pt;
            border: 1px dashed #999;
            border-radius: 3px;
            padding: 5px 8px;
        }
    </style>
</head>
<body>

    <div class="container">
        <!-- Header -->
        <div class="header">
            <div class="header-left">
                <div class="logo-box">MI</div>
                <div class="company-info">
                    <div class="company-name">{{ \App\Models\Setting::get('store_name', config('app.name')) }}</div>
                    <div class="company-desc">Inventory Makan Bergizi Gratis.</div>
                    <div class="company-address">
                        {{ \App\Models\Setting::get('store_address', 'Jl. Default No. 1') }}<br>
                        HP. {{ \App\Models\Setting::get('store_phone', '-') }}
                    </div>
                </div>
            </div>
            <div class="header-right">
                <div class="header-row">
                    <span>{{ $return->return_date->locale('id')->isoFormat('dddd, D MMMM Y') }}</span>
                </div>
                <div class="header-row">
                    <span class="header-label">Kepada Yth,</span>
                    <span class="header-value">{{ $return->sale->customer->name ?? 'Guest' }}</span>
                </div>
                <div class="header-row">
                    <span class="header-label">Kasir</span>
                    <span class="header-value">{{ $return->creator->name ?? '-' }}</span>
                </div>
            </div>
        </div>

        <!-- Return No Line -->
        <div class="invoice-row">
            <span class="invoice-label">BUKTI RETUR No.</span>
            <span class="invoice-value">{{ $return->return_number }}</span>
            &nbsp;&nbsp;
            <span class="invoice-label">Ref. Faktur</span>
            <span class="invoice-value">{{ $return->sale->invoice_number }}</span>
            <span class="return-badge">RETURN RECEIPT</span>
        </div>

        <!-- Table -->
        <table>
            <thead>
                <tr>
                    <th class="col-name">Nama Barang</th>
                    <th class="col-qty">Qty</th>
                    <th class="col-price">Harga Satuan</th>
                    <th class="col-total">Jumlah</th>
                </tr>
            </thead>
            <tbody>
                @foreach($return->items as $item)
                <tr>
                    <td class="col-name">
                        {{ $item->product->name }}
                        @if($item->product->unit)
                            <span style="color:#666;">({{ $item->product->unit->name }})</span>
                        @endif
                    </td>
                    <td class="col-qty">{{ $item->quantity }}</td>
                    <td class="col-price">{{ format_money($item->unit_price) }}</td>
                    <td class="col-total">{{ format_money($item->subtotal) }}</td>
                </tr>
                @endforeach

                {{-- Fill empty rows to maintain size --}}
                @for($i = 0; $i < max(0, 8 - count($return->items)); $i++)
                <tr>
                    <td>&nbsp;</td>
                    <td></td>
                    <td></td>
                    <td></td>
                </tr>
                @endfor
            </tbody>
        </table>

        @if($return->reason)
        <div class="reason-box">
            <strong>Alasan Retur:</strong> {{ $return->reason }}
        </div>
        @endif

        <!-- Footer -->
        <div class="footer">
            <div class="footer-left">
                <div>Tanda Terima Pelanggan</div>
                <div class="signature-space"></div>
                <div>( .................................... )</div>
            </div>

            <div class="footer-center">
                <div class="disclaimer-box">
                    Barang yang dikembalikan telah diperiksa dan disetujui. Pengembalian dana / kredit akan diproses sesuai kebijakan toko.
                </div>
            </div>

            <div class="footer-right">
                <div class="amount-row">
                    <span class="amount-label">Total Refund</span>
                    <span class="amount-value">{{ format_money($return->total_refund) }}</span>
                </div>
                <div style="margin-top: 20px; text-align: center; font-size: 9pt;">
                    <div>Petugas</div>
                    <div class="signature-space"></div>
                    <div>( {{ $return->creator->name ?? '............................' }} )</div>
                </div>
            </div>
        </div>
    </div>

</body>
</html>
