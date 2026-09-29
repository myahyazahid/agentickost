@props(['invoice'])

@php
    use App\Modules\Billing\States\Invoice\Paid;
    use App\Modules\Billing\States\Invoice\Voided;

    [$tone, $label] = match (true) {
        $invoice->status->equals(Paid::class) => ['paid', 'Lunas'],
        $invoice->status->equals(Voided::class) => ['neutral', 'Dibatalkan'],
        $invoice->isOverdue() => ['late', 'Telat '.$invoice->daysLate().' hari'],
        $invoice->paid_amount > 0 => ['pending', 'Dibayar sebagian'],
        default => ['neutral', 'Belum dibayar'],
    };
@endphp

<x-portal::status :tone="$tone">{{ $label }}</x-portal::status>
