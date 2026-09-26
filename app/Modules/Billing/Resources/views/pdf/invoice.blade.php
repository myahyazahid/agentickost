@php
    use App\Modules\Billing\States\Invoice\Paid;
    use App\Modules\Billing\States\Invoice\Voided;
    use App\Support\Money\Rupiah;

    $accent = $tenant->brand_color ?: '#0f766e';
    $date = fn ($value) => $value?->translatedFormat('j F Y');
    $isVoided = $invoice->status->equals(Voided::class);
    $isPaid = $invoice->status->equals(Paid::class);
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Tagihan {{ $invoice->number ?? 'draf' }}</title>
    <style>
        @page { margin: 32px 40px; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #1f2937; line-height: 1.5; }
        .header { border-bottom: 3px solid {{ $accent }}; padding-bottom: 10px; margin-bottom: 18px; }
        .tenant { font-size: 16px; font-weight: bold; color: {{ $accent }}; }
        .muted { color: #4b5563; }
        h1 { font-size: 15px; margin: 0 0 2px; }
        h2 { font-size: 12px; margin: 18px 0 6px; color: {{ $accent }}; }
        table { width: 100%; border-collapse: collapse; }
        td { padding: 3px 0; vertical-align: top; }
        td.label { width: 30%; color: #4b5563; }
        table.items th, table.items td { border-bottom: 1px solid #d1d5db; padding: 6px 6px; text-align: left; }
        table.items th { background: #f3f4f6; }
        table.items .amount { text-align: right; white-space: nowrap; }
        table.totals td { padding: 3px 6px; }
        table.totals td.amount { text-align: right; width: 30%; white-space: nowrap; }
        table.totals tr.due td { font-size: 13px; font-weight: bold; border-top: 2px solid {{ $accent }}; padding-top: 6px; }
        .status { display: inline-block; padding: 2px 8px; border: 1px solid #9ca3af; font-weight: bold; }
        .status.voided { color: #b91c1c; border-color: #b91c1c; }
        .status.paid { color: #047857; border-color: #047857; }
        .note { margin-top: 18px; color: #4b5563; }
    </style>
</head>
<body>
    <div class="header">
        <div class="tenant">{{ $tenant->name }}</div>
        <div class="muted">{{ $invoice->property->name }}, {{ $invoice->property->address }}, {{ $invoice->property->city }}</div>
    </div>

    <table>
        <tr>
            <td>
                <h1>Tagihan</h1>
                <div class="muted">Nomor {{ $invoice->number ?? 'belum terbit' }}</div>
            </td>
            <td style="text-align: right">
                <span class="status {{ $isVoided ? 'voided' : ($isPaid ? 'paid' : '') }}">{{ $invoice->status->getLabel() }}</span>
            </td>
        </tr>
    </table>

    @if ($isVoided)
        <p class="status voided">Tagihan ini dibatalkan pada {{ $date($invoice->voided_at) }}: {{ $invoice->void_reason }}. Jangan dibayar.</p>
    @endif

    <h2>Ditagihkan kepada</h2>
    <table>
        <tr><td class="label">Nama</td><td>{{ $invoice->payer->name }}</td></tr>
        @if ($invoice->contract)
            <tr><td class="label">Kamar</td><td>{{ $invoice->contract->room?->number }}</td></tr>
        @endif
        @if ($invoice->period_start)
            <tr><td class="label">Periode</td><td>{{ $date($invoice->period_start) }} sampai {{ $date($invoice->period_end) }}</td></tr>
        @endif
        <tr><td class="label">Tanggal terbit</td><td>{{ $date($invoice->issue_date) ?? '-' }}</td></tr>
        <tr><td class="label">Jatuh tempo</td><td><strong>{{ $date($invoice->due_date) }}</strong></td></tr>
    </table>

    <h2>Rincian</h2>
    <table class="items">
        <tr><th>Keterangan</th><th class="amount">Jumlah</th></tr>
        @foreach ($invoice->items as $item)
            <tr>
                <td>{{ $item->description }}</td>
                <td class="amount">{{ Rupiah::format($item->amount) }}</td>
            </tr>
        @endforeach
        @foreach ($penalties as $penalty)
            <tr>
                <td>Denda keterlambatan {{ $date($penalty->accrued_on) }}</td>
                <td class="amount">{{ Rupiah::format($penalty->amount) }}</td>
            </tr>
        @endforeach
    </table>

    <table class="totals" style="margin-top: 8px">
        <tr><td>Total tagihan</td><td class="amount">{{ Rupiah::format($invoice->items_total_amount + $invoice->penalty_amount) }}</td></tr>
        @if ($invoice->credited_amount > 0)
            <tr><td>Nota kredit</td><td class="amount">-{{ Rupiah::format($invoice->credited_amount) }}</td></tr>
        @endif
        @if ($invoice->paid_amount > 0)
            <tr><td>Sudah dibayar</td><td class="amount">-{{ Rupiah::format($invoice->paid_amount) }}</td></tr>
        @endif
        <tr class="due"><td>Sisa yang harus dibayar</td><td class="amount">{{ Rupiah::format($isVoided ? 0 : max(0, $invoice->balance_amount)) }}</td></tr>
    </table>

    @if (! $isVoided && ! $isPaid && $accounts->isNotEmpty())
        <h2>Cara bayar</h2>
        <table>
            @foreach ($accounts as $account)
                <tr><td class="label">{{ $account->provider_name }}</td><td>{{ $account->account_number }} a.n. {{ $account->account_holder }}</td></tr>
            @endforeach
        </table>
        <p class="note">Tulis nomor tagihan {{ $invoice->number }} di berita transfer, lalu kirim bukti transfer ke pengelola.</p>
    @endif
</body>
</html>
