@php
    use App\Modules\Payment\States\Payment\Pending;
    use App\Modules\Payment\States\Payment\Rejected;
    use App\Modules\Payment\States\Payment\Reversed;
    use App\Modules\Payment\States\Payment\Verified;
    use App\Support\Money\Rupiah;
@endphp

<div>
    <h1 class="text-2xl font-semibold tracking-tight">Riwayat pembayaran</h1>

    <ul class="mt-4 space-y-3">
        @forelse ($payments as $payment)
            @php
                [$tone, $label] = match (true) {
                    $payment->status->equals(Verified::class) => ['paid', 'Diterima'],
                    $payment->status->equals(Pending::class) => ['pending', 'Sedang diperiksa'],
                    $payment->status->equals(Rejected::class) => ['late', 'Ditolak'],
                    $payment->status->equals(Reversed::class) => ['neutral', 'Dibatalkan'],
                    default => ['neutral', $payment->status->getLabel()],
                };
                $receipt = $receiptUrl($payment);
            @endphp
            <li class="rounded-lg bg-white p-4 ring-1 ring-line">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <p class="font-semibold tabular-nums">{{ Rupiah::format($payment->amount) }}</p>
                        <p class="mt-0.5 text-sm text-muted">
                            {{ $payment->paid_at->timezone($portalTenant->default_timezone)->translatedFormat('j F Y') }},
                            {{ $payment->method->getLabel() }}, kamar {{ $payment->contract?->room?->number }}
                        </p>
                    </div>
                    <x-portal::status :tone="$tone">{{ $label }}</x-portal::status>
                </div>

                @if ($payment->status->equals(Rejected::class) && $payment->rejection_reason)
                    <p class="mt-2 text-sm">Alasan: {{ $payment->rejection_reason }}</p>
                @endif

                @if ($receipt)
                    <a href="{{ $receipt }}" target="_blank" rel="noopener" class="mt-2 inline-flex min-h-11 items-center gap-1 text-sm font-medium underline underline-offset-2">
                        <x-heroicon-o-arrow-down-tray class="size-4" aria-hidden="true" />
                        Kuitansi {{ $payment->receipt_number }}
                    </a>
                @endif
            </li>
        @empty
            <li class="rounded-lg bg-white p-5 text-sm ring-1 ring-line">
                Belum ada pembayaran. Setelah Anda mengirim bukti transfer dari halaman tagihan, statusnya muncul di sini.
            </li>
        @endforelse
    </ul>
</div>
