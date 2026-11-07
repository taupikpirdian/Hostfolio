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
        .top, .summary, .items, .totals, .invoice-meta { border-collapse: collapse; width: 100%; }
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
        .status-pill { background: #fff1d6; border-radius: 14px; color: #9a5700; display: inline-block; font-size: 9px; font-weight: bold; padding: 5px 13px; }
        .header-rule { border-bottom: 2px solid #c9d8ff; margin: 27px 0 30px; position: relative; }
        .header-rule:before { background: #285be8; border-radius: 3px; content: ''; height: 4px; left: 0; position: absolute; top: -1px; width: 90px; }
        .hero { margin-top: 55px; width: 100%; }
        .hero td { vertical-align: bottom; }
        .eyebrow { color: #285be8; font-size: 12px; font-weight: bold; letter-spacing: 1.5px; }
        h1 { font-size: 44px; letter-spacing: -2px; margin: 4px 0; }
        .doc-number { font-size: 17px; font-weight: bold; margin-top: 7px; }
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
        .center { text-align: center; }
        .description { font-size: 11px; font-weight: bold; }
        .spec { color: #7183a1; font-size: 9px; line-height: 1.5; margin-top: 5px; }
        .source { color: #7183a1; font-size: 9px; margin-bottom: 5px; }
        .bottom { margin-top: 25px; width: 100%; }
        .bottom td { vertical-align: top; }
        .payment-box { background: #f7f9ff; border: 1px solid #dce5fa; border-radius: 10px; padding: 16px; }
        .payment-box strong { display: block; font-size: 12px; margin-bottom: 10px; }
        .totals { font-size: 10px; }
        .totals td { padding: 6px 4px; }
        .grand td { background: #edf2ff; color: #2149b9; font-size: 13px; font-weight: bold; padding: 13px 10px; }
        .outstanding td { border-top: 1px solid #dce5f1; font-size: 12px; font-weight: bold; padding-top: 10px; }
        .empty { background: #f8fafc; color: #7183a1; padding: 22px; text-align: center; }
        .footer { border-top: 1px solid #dce5f1; color: #7183a1; font-size: 9px; margin-top: 65px; padding-top: 12px; }
    </style>
</head>
<body>
    @php
        $formatAmount = fn ($amount): string => number_format((float) $amount, 0, ',', '.');
        $currencyLabel = fn (string $currency): string => $currency === 'IDR' ? 'IDR (Rupiah)' : $currency;
        $invoiceCount = $invoices->count();
        $organizationName = filled($invoiceSettings['organization_name'] ?? null) ? $invoiceSettings['organization_name'] : config('app.name');
        $issuerDetails = array_filter([$invoiceSettings['contact_email'] ?? null, $invoiceSettings['address'] ?? null], fn ($value): bool => filled($value));
        $paymentInformation = filled($invoiceSettings['payment_information'] ?? null) ? $invoiceSettings['payment_information'] : 'Detail rekening atau instruksi pembayaran dapat ditambahkan melalui pengaturan penagihan '.$organizationName.'. Cantumkan nomor invoice saat mengonfirmasi pembayaran.';
        $invoiceNumber = $invoiceCount === 1 ? $invoices->first()->number : ($invoiceCount > 1 ? $invoiceCount.' invoice digabung' : '—');
        $documentStatus = $invoices->contains(fn ($invoice): bool => bccomp($invoice->outstanding, '0', 2) > 0) ? 'BELUM LUNAS' : 'TIDAK ADA TAGIHAN';
        $dateLabel = $invoiceCount === 1 ? 'Tanggal Terbit' : 'Tanggal Cetak';
        $dateValue = $invoiceCount === 1 ? $invoices->first()->issue_date?->format('d M Y') : now()->format('d M Y');
    @endphp
    <div class="topline"></div>
    <table class="top"><tr>
        <td style="width:55%"><span class="brand-mark">◆</span><span class="brand">{{ $organizationName }}</span><div class="brand-sub muted">INVENTORY &amp; BILLING</div>@if ($issuerDetails)<div class="brand-sub muted" style="letter-spacing:0">{{ implode(' · ', $issuerDetails) }}</div>@endif</td>
        <td style="width:45%" class="right">
            <div><span class="document-pill">INVOICE CONTRACT</span></div>
            <table class="invoice-meta">
                <tr><td class="meta-label">No. Invoice</td><td class="meta-separator">:</td><td class="meta-value">{{ $invoiceNumber }}</td></tr>
                <tr><td class="meta-label">{{ $dateLabel }}</td><td class="meta-separator">:</td><td class="meta-value">{{ $dateValue ?? '—' }}</td></tr>
                <tr><td class="meta-label">Status Tagihan</td><td class="meta-separator">:</td><td class="meta-value"><span class="status-pill">{{ $documentStatus }}</span></td></tr>
            </table>
        </td>
    </tr></table>

    <div class="header-rule"></div>

    <h2>Rincian Tagihan <span class="muted" style="float:right;font-size:9px;font-weight:normal">{{ $invoices->sum(fn ($invoice) => $invoice->items->count()) }} item · {{ $invoiceCount }} invoice</span></h2>
    <table class="items">
        <thead><tr><th style="width:32%">DESKRIPSI</th><th style="width:16%">INVOICE</th><th style="width:19%">PERIODE</th><th style="width:8%">QTY</th><th class="right" style="width:13%">HARGA SATUAN</th><th class="right" style="width:12%">JUMLAH</th></tr></thead>
        <tbody>
        @forelse ($invoices as $invoice)
            @foreach ($invoice->items as $item)
                @php
                    $target = $item->target;
                    $allocation = null;
                    if ($target instanceof \App\Models\ComputeInstance) {
                        $allocation = $target->currentAllocation;
                    } elseif ($target instanceof \App\Models\Service) {
                        $target->loadMissing('deployments.instance.currentAllocation');
                        $deployment = $target->deployments->sortByDesc('deployed_at')->first();
                        $allocation = $deployment?->instance?->currentAllocation;
                    }
                    $specs = $allocation ? array_filter([
                        $allocation->vcpu !== null ? 'vCPU: '.number_format((float) $allocation->vcpu, 2, '.', '') : null,
                        $allocation->ram_mb !== null ? 'RAM: '.number_format((float) $allocation->ram_mb, 0, '.', '').' MB' : null,
                        $allocation->storage_gb !== null ? 'Storage: '.number_format((float) $allocation->storage_gb, 2, '.', '').' GB' : null,
                    ]) : [];
                    $currency = $invoice->currency ?? $invoice->source_currency ?? 'IDR';
                @endphp
                <tr>
                    <td><div class="description">{{ $item->description }}</div>@if ($specs)<div class="spec">{{ implode(' · ', $specs) }}</div>@endif</td>
                    <td><strong>{{ $invoice->number }}</strong><div class="source">{{ bccomp($invoice->total, '0', 2) === 0 ? 'TANPA TAGIHAN' : ($invoice->payment_status === 'partial' ? 'DIBAYAR SEBAGIAN' : 'BELUM LUNAS') }}</div></td>
                    <td>{{ $invoice->period_start?->format('d M Y') ?? '—' }}<br>– {{ $invoice->period_end?->format('d M Y') ?? '—' }}</td>
                    <td>{{ rtrim(rtrim(number_format((float) $item->quantity, 4, ',', '.'), '0'), ',') }}</td>
                    <td class="right">{{ $currency }} {{ $formatAmount($item->unit_price) }}</td>
                    <td class="right">{{ $currency }} {{ $formatAmount($item->total) }}</td>
                </tr>
            @endforeach
        @empty
            <tr><td colspan="6" class="empty">Tidak ada invoice yang belum lunas atau bernilai 0.</td></tr>
        @endforelse
        </tbody>
    </table>

    <table class="bottom"><tr>
        <td style="width:47%;padding-right:28px"><div class="payment-box"><strong>Informasi Pembayaran</strong><div class="muted" style="white-space:pre-line">{{ $paymentInformation }}</div></div></td>
        <td style="width:53%"><table class="totals">
            @foreach ($currencyTotals as $currency => $totals)
                <tr><td colspan="2" class="muted" style="padding-top:12px"><strong>{{ $currencyLabel($currency) }}</strong></td></tr>
                <tr><td>Total nilai invoice</td><td class="right">{{ $currency }} {{ $formatAmount($totals['billed']) }}</td></tr>
                <tr><td>Telah dibayar</td><td class="right">{{ $currency }} {{ $formatAmount($totals['paid']) }}</td></tr>
                <tr class="grand"><td>Sisa tagihan</td><td class="right">{{ $currency }} {{ $formatAmount($totals['outstanding']) }}</td></tr>
            @endforeach
        </table></td>
    </tr></table>

    <div class="footer">{{ filled($invoiceSettings['invoice_footer'] ?? null) ? $invoiceSettings['invoice_footer'] : 'Dokumen ringkasan invoice diterbitkan melalui '.$organizationName.'. Setiap invoice tetap menjadi dokumen tagihan tersendiri.' }}</div>

</body>
</html>
