<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 38px 42px; }
        body { color: #14213d; font-family: DejaVu Sans, sans-serif; font-size: 10px; }
        .topline { background: #2563eb; height: 5px; left: 0; position: fixed; right: 0; top: -38px; }
        .muted { color: #7183a1; }
        .blue { color: #285be8; }
        .top, .items, .bottom, .totals, .invoice-meta { border-collapse: collapse; width: 100%; }
        .top td { vertical-align: middle; }
        .brand { font-size: 22px; font-weight: bold; letter-spacing: -.5px; }
        .brand-mark { background: #eaf0ff; border-radius: 10px; color: #285be8; display: inline-block; font-size: 17px; font-weight: bold; margin-right: 9px; padding: 11px 12px; }
        .brand-sub { font-size: 9px; letter-spacing: 1px; margin-left: 52px; margin-top: 2px; }
        .document-pill { background: #edf2ff; border-radius: 20px; color: #285be8; font-size: 10px; letter-spacing: 2px; padding: 12px 18px; }
        .invoice-meta { font-size: 10px; margin-top: 18px; }
        .invoice-meta td { padding: 5px 3px; }
        .invoice-meta .meta-label { color: #7183a1; text-align: left; width: 110px; }
        .invoice-meta .meta-separator { color: #7183a1; text-align: center; width: 18px; }
        .invoice-meta .meta-value { font-weight: bold; text-align: left; }
        .status-pill { background: #e4f6ef; border-radius: 14px; color: #07875e; display: inline-block; font-size: 9px; font-weight: bold; padding: 5px 13px; }
        .status-pill.draft { background: #edf2ff; color: #285be8; }
        .status-pill.void { background: #fee2e2; color: #b91c1c; }
        .status-pill.unpaid { background: #fff1d6; color: #9a5700; }
        .status-pill.partial { background: #fff1d6; color: #9a5700; }
        .header-rule { border-bottom: 2px solid #c9d8ff; margin: 27px 0 24px; position: relative; }
        .header-rule:before { background: #285be8; border-radius: 3px; content: ''; height: 4px; left: 0; position: absolute; top: -1px; width: 90px; }
        .recipient { color: #7183a1; font-size: 10px; margin-bottom: 25px; }
        .recipient strong { color: #14213d; font-size: 12px; }
        h2 { font-size: 16px; margin: 0 0 12px; }
        .items th { background: #edf2fa; color: #405675; font-size: 9px; letter-spacing: .5px; text-align: left; }
        .items th, .items td { padding: 10px 8px; }
        .items td { border-bottom: 1px solid #e5ebf3; vertical-align: top; }
        .right { text-align: right; }
        .description { font-size: 11px; font-weight: bold; }
        .spec { color: #7183a1; font-size: 9px; line-height: 1.5; margin-top: 5px; }
        .bottom { margin-top: 25px; }
        .bottom td { vertical-align: top; }
        .payment-box { background: #f7f9ff; border: 1px solid #dce5fa; border-radius: 10px; padding: 16px; }
        .payment-box strong { display: block; font-size: 12px; margin-bottom: 10px; }
        .totals { font-size: 10px; }
        .totals td { padding: 6px 4px; }
        .grand td { background: #edf2ff; color: #2149b9; font-size: 13px; font-weight: bold; padding: 13px 10px; }
        .outstanding td { border-top: 1px solid #dce5f1; font-size: 12px; font-weight: bold; padding-top: 10px; }
        .footer { border-top: 1px solid #dce5f1; color: #7183a1; font-size: 9px; margin-top: 65px; padding-top: 12px; }
    </style>
</head>
<body>
    @php
        $formatAmount = fn ($amount): string => number_format((float) $amount, 0, ',', '.');
        $currency = $invoice->currency ?? $invoice->source_currency ?? 'IDR';
        $currencyLabel = $currency === 'IDR' ? 'IDR (Rupiah)' : $currency;
        $organizationName = filled($invoiceSettings['organization_name'] ?? null) ? $invoiceSettings['organization_name'] : config('app.name');
        $issuerDetails = array_filter([$invoiceSettings['contact_email'] ?? null, $invoiceSettings['address'] ?? null], fn ($value): bool => filled($value));
        $paymentInformation = filled($invoiceSettings['payment_information'] ?? null) ? $invoiceSettings['payment_information'] : 'Detail rekening atau instruksi pembayaran dapat ditambahkan melalui pengaturan penagihan '.$organizationName.'. Cantumkan nomor invoice saat mengonfirmasi pembayaran.';
        $statusLabel = match ($invoice->status) {
            'draft' => 'DRAF',
            'void' => 'DIBATALKAN',
            default => bccomp($invoice->total, '0', 2) === 0 ? 'TANPA TAGIHAN' : match ($invoice->payment_status) {
                'paid' => 'LUNAS',
                'partial' => 'DIBAYAR SEBAGIAN',
                default => 'BELUM LUNAS',
            },
        };
        $statusClass = match ($invoice->status) {
            'draft' => 'draft',
            'void' => 'void',
            default => $invoice->payment_status === 'partial' ? 'partial' : (bccomp($invoice->outstanding, '0', 2) > 0 ? 'unpaid' : ''),
        };
        $statusCaption = in_array($invoice->status, ['draft', 'void'], true) ? 'Status Dokumen' : 'Status Pembayaran';
        $specification = function ($target): array {
            if ($target instanceof \App\Models\ComputeInstance) {
                $allocation = $target->currentAllocation;
            } elseif ($target instanceof \App\Models\Service) {
                $target->loadMissing('deployments.instance.currentAllocation');
                $deployment = $target->deployments->sortByDesc('deployed_at')->first();
                $allocation = $deployment?->instance?->currentAllocation;
            } else {
                return [];
            }

            if (! $allocation) {
                return [];
            }

            return array_filter([
                $allocation->vcpu !== null ? 'vCPU: '.number_format((float) $allocation->vcpu, 2, '.', '') : null,
                $allocation->ram_mb !== null ? 'RAM: '.number_format((float) $allocation->ram_mb, 0, '.', '').' MB' : null,
                $allocation->storage_gb !== null ? 'Storage: '.number_format((float) $allocation->storage_gb, 2, '.', '').' GB' : null,
            ]);
        };
    @endphp
    <div class="topline"></div>
    <table class="top"><tr>
        <td style="width:55%"><span class="brand-mark">◆</span><span class="brand">{{ $organizationName }}</span><div class="brand-sub muted">INVENTORY &amp; BILLING</div>@if ($issuerDetails)<div class="brand-sub muted" style="letter-spacing:0">{{ implode(' · ', $issuerDetails) }}</div>@endif</td>
        <td style="width:45%" class="right"><div><span class="document-pill">INVOICE</span></div>
            <table class="invoice-meta">
                <tr><td class="meta-label">No. Invoice</td><td class="meta-separator">:</td><td class="meta-value">{{ $invoice->number }}</td></tr>
                <tr><td class="meta-label">Tanggal Terbit</td><td class="meta-separator">:</td><td class="meta-value">{{ $invoice->issue_date?->format('d M Y') ?? '—' }}</td></tr>
                <tr><td class="meta-label">{{ $statusCaption }}</td><td class="meta-separator">:</td><td class="meta-value"><span class="status-pill {{ $statusClass }}">{{ $statusLabel }}</span></td></tr>
            </table>
        </td>
    </tr></table>

    <div class="header-rule"></div>
    <div class="recipient">{{ $invoice->type === 'payable' ? 'DITAGIHKAN OLEH' : 'DITAGIHKAN KEPADA' }} &nbsp; <strong>{{ $invoice->party->name }}</strong> &nbsp; {{ $invoice->party->email }} &nbsp; {{ $invoice->party->address }}</div>

    <h2>Rincian Tagihan <span class="muted" style="float:right;font-size:9px;font-weight:normal">{{ $invoice->items->count() }} item · 1 invoice</span></h2>
    <table class="items">
        <thead><tr><th style="width:45%">DESKRIPSI</th><th style="width:20%">PERIODE</th><th style="width:8%">QTY</th><th class="right" style="width:14%">HARGA SATUAN</th><th class="right" style="width:13%">JUMLAH</th></tr></thead>
        <tbody>
        @foreach ($invoice->items as $item)
            @php($specs = $specification($item->target))
            <tr>
                <td><div class="description">{{ $item->description }}</div>@if ($specs)<div class="spec">{{ implode(' · ', $specs) }}</div>@endif</td>
                <td>{{ $invoice->period_start?->format('d M Y') ?? '—' }}<br>– {{ $invoice->period_end?->format('d M Y') ?? '—' }}</td>
                <td>{{ rtrim(rtrim(number_format((float) $item->quantity, 4, ',', '.'), '0'), ',') }}</td>
                <td class="right">{{ $currency }} {{ $formatAmount($item->unit_price) }}</td>
                <td class="right">{{ $currency }} {{ $formatAmount($item->total) }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>

    <div class="rule" style="margin:24px 0"></div>
    <table class="bottom"><tr>
        <td style="width:48%;padding-right:28px"><div class="payment-box"><strong>Informasi Pembayaran</strong><div class="muted" style="white-space:pre-line">{{ $paymentInformation }}</div></div></td>
        <td style="width:52%"><table class="totals">
            <tr><td>Subtotal</td><td class="right">{{ $currency }} {{ $formatAmount($invoice->subtotal) }}</td></tr>
            <tr><td>Diskon</td><td class="right">{{ $currency }} {{ $formatAmount($invoice->discount) }}</td></tr>
            <tr><td>Pajak</td><td class="right">{{ $currency }} {{ $formatAmount($invoice->tax) }}</td></tr>
            <tr class="grand"><td>Total Tagihan</td><td class="right">{{ $currency }} {{ $formatAmount($invoice->total) }}</td></tr>
            <tr><td>Telah dibayar</td><td class="right">{{ $currency }} {{ $formatAmount($invoice->paid_total) }}</td></tr>
            <tr class="outstanding"><td>Sisa Tagihan</td><td class="right">{{ $currency }} {{ $formatAmount($invoice->status === 'issued' ? $invoice->outstanding : $invoice->total) }}</td></tr>
        </table></td>
    </tr></table>

    @if ($invoice->notes)<h2>Catatan</h2><p>{{ $invoice->notes }}</p>@endif
    <div class="footer">{{ filled($invoiceSettings['invoice_footer'] ?? null) ? $invoiceSettings['invoice_footer'] : 'Invoice diterbitkan melalui '.$organizationName.'. Mohon cantumkan nomor invoice saat mengonfirmasi pembayaran.' }}<span style="float:right">{{ $invoice->number }}</span></div>
</body>
</html>
