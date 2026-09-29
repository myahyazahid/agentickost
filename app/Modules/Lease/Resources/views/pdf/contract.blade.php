@php
    use App\Modules\Tenancy\Support\TenantBranding;
    use App\Support\Money\Rupiah;

    $accent = $tenant->brand_color ?: '#0f766e';
    $logo = TenantBranding::logoDataUri($tenant);
    $date = fn ($value) => $value?->translatedFormat('j F Y');
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Kontrak {{ $contract->number ?? 'draf' }}</title>
    <style>
        @page { margin: 32px 40px; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #1f2937; line-height: 1.5; }
        .header { border-bottom: 3px solid {{ $accent }}; padding-bottom: 10px; margin-bottom: 18px; }
        .tenant { font-size: 16px; font-weight: bold; color: {{ $accent }}; }
        .property { color: #4b5563; }
        h1 { font-size: 15px; margin: 0 0 4px; }
        .number { color: #4b5563; margin-bottom: 16px; }
        h2 { font-size: 12px; margin: 18px 0 6px; color: {{ $accent }}; }
        table { width: 100%; border-collapse: collapse; }
        td { padding: 3px 0; vertical-align: top; }
        td.label { width: 34%; color: #4b5563; }
        table.list th, table.list td { border: 1px solid #d1d5db; padding: 5px 6px; text-align: left; }
        table.list th { background: #f3f4f6; }
        .clauses { white-space: pre-line; }
        .draft { color: #b91c1c; font-weight: bold; }
        .signatures td { width: 50%; padding-top: 48px; text-align: center; }
        .signatures .line { border-top: 1px solid #1f2937; margin: 0 24px; padding-top: 4px; }
    </style>
</head>
<body>
    <div class="header">
        @if ($logo)
            <img src="{{ $logo }}" alt="" style="max-height: 48px; max-width: 180px; margin-bottom: 6px">
        @endif
        <div class="tenant">{{ $tenant->name }}</div>
        <div class="property">{{ $contract->property->name }}, {{ $contract->property->address }}, {{ $contract->property->city }}</div>
    </div>

    <h1>Perjanjian Sewa Kamar</h1>
    <div class="number">
        @if ($contract->number)
            Nomor {{ $contract->number }}
        @else
            <span class="draft">Draf, belum berlaku</span>
        @endif
    </div>

    <h2>Kamar</h2>
    <table>
        <tr><td class="label">Nomor kamar</td><td>{{ $contract->room->number }} ({{ $contract->room->roomType?->name }})</td></tr>
        <tr><td class="label">Properti</td><td>{{ $contract->property->name }}</td></tr>
    </table>

    <h2>Penghuni</h2>
    <table class="list">
        <tr><th>Nama</th><th>Telepon</th><th>Keterangan</th></tr>
        @foreach ($occupants as $occupant)
            <tr>
                <td>{{ $occupant->full_name }}</td>
                <td>{{ $occupant->phone }}</td>
                <td>{{ $occupant->pivot->is_primary ? 'Penghuni utama' : 'Penghuni' }}</td>
            </tr>
        @endforeach
    </table>

    <h2>Pembayar</h2>
    <table>
        <tr><td class="label">Nama</td><td>{{ $contract->payer->name }} ({{ $contract->payer->relation->getLabel() }})</td></tr>
        <tr><td class="label">Telepon</td><td>{{ $contract->payer->phone }}</td></tr>
    </table>

    <h2>Masa sewa dan biaya</h2>
    <table>
        <tr><td class="label">Mulai</td><td>{{ $date($contract->start_date) }}</td></tr>
        <tr><td class="label">Selesai</td><td>{{ $contract->end_date ? $date($contract->end_date) : 'Sampai salah satu pihak mengakhiri' }}</td></tr>
        <tr><td class="label">Periode bayar</td><td>{{ $contract->rental_period->getLabel() }}</td></tr>
        <tr><td class="label">Sewa per periode</td><td>{{ Rupiah::format($contract->rent_amount) }}</td></tr>
        <tr><td class="label">Deposit</td><td>{{ Rupiah::format($contract->deposit_amount) }}</td></tr>
        @if ($contract->early_termination_penalty_amount)
            <tr><td class="label">Denda pemutusan dini</td><td>{{ Rupiah::format($contract->early_termination_penalty_amount) }}</td></tr>
        @endif
    </table>

    @if ($contract->holds->isNotEmpty())
        <h2>Tarif khusus masa libur</h2>
        <table class="list">
            <tr><th>Periode</th><th>Sewa per periode</th><th>Keterangan</th></tr>
            @foreach ($contract->holds as $hold)
                <tr>
                    <td>{{ $date($hold->start_date) }} sampai {{ $date($hold->end_date) }}</td>
                    <td>{{ Rupiah::format($hold->rent_amount) }}</td>
                    <td>{{ $hold->reason }}</td>
                </tr>
            @endforeach
        </table>
    @endif

    @if ($contract->clauses)
        <h2>Ketentuan tambahan</h2>
        <div class="clauses">{{ $contract->clauses }}</div>
    @endif

    @if ($contract->property->rules)
        <h2>Aturan kost</h2>
        <div class="clauses">{{ $contract->property->rules }}</div>
    @endif

    <table class="signatures">
        <tr>
            <td><div class="line">Pengelola, {{ $tenant->name }}</div></td>
            <td><div class="line">Penghuni, {{ $occupants->first()?->full_name }}</div></td>
        </tr>
    </table>
</body>
</html>
