@php
    use App\Modules\Payment\Enums\PaymentMethod;
    use App\Modules\Payment\States\Payment\Reversed;
    use App\Modules\Tenancy\Support\TenantBranding;
    use App\Support\Money\Rupiah;

    $accent = $tenant->brand_color ?: '#0f766e';
    $logo = TenantBranding::logoDataUri($tenant);
    $timezone = $payment->property->timezone->value;
    $isReversed = $payment->status->equals(Reversed::class);
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Kuitansi {{ $payment->receipt_number }}</title>
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
        .total { font-size: 14px; font-weight: bold; }
        .cancelled { color: #b91c1c; border: 1px solid #b91c1c; padding: 4px 8px; font-weight: bold; }
    </style>
</head>
<body>
    <div class="header">
        @if ($logo)
            <img src="{{ $logo }}" alt="" style="max-height: 48px; max-width: 180px; margin-bottom: 6px">
        @endif
        <div class="tenant">{{ $tenant->name }}</div>
        <div class="muted">{{ $payment->property->name }}, {{ $payment->property->address }}, {{ $payment->property->city }}</div>
    </div>

    <h1>Kuitansi</h1>
    <div class="muted">Nomor {{ $payment->receipt_number }}</div>

    @if ($isReversed)
        <p class="cancelled">Pembayaran ini dibatalkan pada {{ $payment->reversed_at?->timezone($timezone)->translatedFormat('j F Y') }}: {{ $payment->reversal_reason }}. Kuitansi ini tidak berlaku.</p>
    @endif

    <h2>Sudah terima dari</h2>
    <table>
        <tr><td class="label">Nama</td><td>{{ $payment->payer?->name }}</td></tr>
        @if ($payment->contract)
            <tr><td class="label">Kamar</td><td>{{ $payment->contract->room?->number }} (kontrak {{ $payment->contract->number }})</td></tr>
        @endif
        <tr><td class="label">Uang sejumlah</td><td class="total">{{ Rupiah::format($payment->amount) }}</td></tr>
        <tr><td class="label">Tanggal bayar</td><td>{{ $payment->paid_at->timezone($timezone)->translatedFormat('j F Y H:i') }}</td></tr>
        <tr>
            <td class="label">Cara bayar</td>
            <td>
                {{ $payment->method->getLabel() }}
                @if ($payment->method === PaymentMethod::Transfer && $payment->bankAccount)
                    ke {{ $payment->bankAccount->displayName() }}
                @elseif ($payment->method === PaymentMethod::Cash && $payment->receivedBy)
                    diterima {{ $payment->receivedBy->name }}
                @endif
                @if ($payment->reference)
                    , ref. {{ $payment->reference }}
                @endif
            </td>
        </tr>
    </table>

    <h2>Untuk pembayaran</h2>
    <table class="items">
        <tr><th>Tagihan</th><th>Bagian</th><th class="amount">Jumlah</th></tr>
        @foreach ($allocations as $allocation)
            <tr>
                <td>{{ $allocation->invoice->number }}</td>
                <td>{{ $allocation->allocation_category->getLabel() }}@if ($allocation->reversed_at) (dibatalkan)@endif</td>
                <td class="amount">{{ Rupiah::format($allocation->amount) }}</td>
            </tr>
        @endforeach
        @if ($credited > 0)
            <tr>
                <td colspan="2">Disimpan sebagai saldo kredit untuk tagihan berikutnya</td>
                <td class="amount">{{ Rupiah::format($credited) }}</td>
            </tr>
        @endif
    </table>

    <p class="muted" style="margin-top: 18px">Diverifikasi {{ $payment->verified_at?->timezone($timezone)->translatedFormat('j F Y H:i') }}.</p>
</body>
</html>
