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
        .top, .summary, .items, .bottom, .totals { border-collapse: collapse; width: 100%; }
        .top td { vertical-align: top; }
        .brand { font-size: 22px; font-weight: bold; letter-spacing: -.5px; }
        .brand-mark { background: #eaf0ff; border-radius: 10px; color: #285be8; display: inline-block; font-size: 17px; font-weight: bold; margin-right: 9px; padding: 11px 12px; }
        .brand-sub { font-size: 9px; letter-spacing: 1px; margin-left: 52px; margin-top: 2px; }
        .document-pill { background: #edf2ff; border-radius: 20px; color: #285be8; font-size: 10px; letter-spacing: 2px; padding: 12px 18px; }
        .hero { margin-top: 55px; width: 100%; }
        .hero td { vertical-align: bottom; }
        .eyebrow { color: #285be8; font-size: 12px; font-weight: bold; letter-spacing: 1.5px; }
        h1 { font-size: 46px; letter-spacing: -2px; margin: 4px 0; }
        .doc-number { font-size: 17px; font-weight: bold; margin-top: 7px; }
        .status { background: #e4f6ef; border-radius: 7px; color: #07875e; font-size: 10px; margin-top: 12px; padding: 8px 10px; text-align: left; }
        .rule { border-top: 1px solid #dce5f1; margin: 28px 0; }
        .summary td { vertical-align: top; width: 50%; }
        .label { color: #7183a1; font-size: 10px; font-weight: bold; letter-spacing: .8px; margin-bottom: 12px; }
        .party { font-size: 20px; font-weight: bold; margin-bottom: 7px; }
        .meta-row { margin: 0 0 9px; }
        .meta-row .value { float: right; font-weight: bold; }
        h2 { font-size: 16px; margin: 34px 0 12px; }
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
        <td><span class="brand-mark">◆</span><span class="brand">{{ $organizationName }}</span><div class="brand-sub muted">{{ implode(' · ', $issuerDetails) }}</div></td>
        <td class="right"><span class="document-pill">INVOICE DOCUMENT</span></td>
    </tr></table>

    <table class="hero"><tr>
        <td><div class="eyebrow">TAGIHAN LAYANAN</div><h1>Invoice</h1><div class="muted" style="font-size:13px">Rincian biaya layanan infrastruktur</div></td>
        <td class="right"><div class="label">NOMOR INVOICE</div><div class="doc-number">{{ $invoice->number }}</div><div class="status">● &nbsp;{{ strtoupper($invoice->status) }}</div></td>
    </tr></table>

    <div class="rule"></div>
    <table class="summary"><tr>
        <td style="padding-right:24px"><div class="label">{{ $invoice->type === 'payable' ? 'DITAGIHKAN OLEH' : 'DITAGIHKAN KEPADA' }}</div><div class="party">{{ $invoice->party->name }}</div><div class="muted">{{ $invoice->party->email }}<br>{{ $invoice->party->address }}</div></td>
        <td style="padding-left:24px"><div class="label">INFORMASI INVOICE</div>
            <div class="meta-row">Tanggal terbit <span class="value">{{ $invoice->issue_date?->format('d M Y') ?? '—' }}</span></div>
            <div class="meta-row">Jatuh tempo <span class="value">{{ $invoice->due_date?->format('d M Y') ?? '—' }}</span></div>
            <div class="meta-row">Periode tagihan <span class="value">{{ $invoice->period_start?->format('d M Y') ?? '—' }} – {{ $invoice->period_end?->format('d M Y') ?? '—' }}</span></div>
            <div class="meta-row">Mata uang <span class="value">{{ $currencyLabel }}</span></div>
        </td>
    </tr></table>

    <h2>Rincian Tagihan <span class="muted" style="float:right;font-size:9px;font-weight:normal">{{ $invoice->items->count() }} item</span></h2>
    <table class="items">
        <thead><tr><th style="width:50%">DESKRIPSI</th><th style="width:12%">QTY</th><th class="right" style="width:19%">HARGA SATUAN</th><th class="right" style="width:19%">JUMLAH</th></tr></thead>
        <tbody>
        @foreach ($invoice->items as $item)
            @php($specs = $specification($item->target))
            <tr>
                <td><div class="description">{{ $item->description }}</div>@if ($specs)<div class="spec">{{ implode(' · ', $specs) }}</div>@endif</td>
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
