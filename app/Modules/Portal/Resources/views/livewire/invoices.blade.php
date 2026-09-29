@php
    use App\Support\Money\Rupiah;
@endphp

<div>
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h1 class="text-2xl font-semibold tracking-tight">Tagihan</h1>

        <div role="group" aria-label="Tampilkan" class="inline-flex rounded-md bg-white p-1 ring-1 ring-line">
            @foreach (['belum-lunas' => 'Belum lunas', 'semua' => 'Semua'] as $value => $label)
                <button
                    type="button"
                    wire:click="$set('show', '{{ $value }}')"
                    aria-pressed="{{ $show === $value ? 'true' : 'false' }}"
                    class="min-h-11 rounded px-3 text-sm font-medium focus-visible:outline-2 focus-visible:outline-(--accent) {{ $show === $value ? 'bg-(--accent) text-(--accent-text)' : 'text-muted hover:text-ink' }}"
                >{{ $label }}</button>
            @endforeach
        </div>
    </div>

    <div wire:loading.delay class="mt-4 text-sm text-muted">Memuat tagihan...</div>

    <ul wire:loading.remove class="mt-4 space-y-3">
        @forelse ($invoices as $invoice)
            <li>
                <a
                    href="{{ route('portal.invoices.show', ['invoice' => $invoice->id]) }}"
                    class="block rounded-lg bg-white p-4 ring-1 ring-line hover:ring-(--accent) focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-(--accent)"
                >
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="font-medium">
                                @if ($invoice->period_start)
                                    {{ $invoice->period_start->translatedFormat('j M') }} sampai {{ $invoice->period_end?->translatedFormat('j M Y') }}
                                @else
                                    {{ $invoice->number }}
                                @endif
                            </p>
                            <p class="mt-0.5 truncate text-sm text-muted">Kamar {{ $invoice->contract?->room?->number }}, {{ $invoice->property?->name }}</p>
                        </div>
                        <x-portal::invoice-status :invoice="$invoice" />
                    </div>
                    <div class="mt-3 flex flex-wrap items-baseline justify-between gap-x-3 text-sm">
                        <span class="text-muted">Jatuh tempo {{ $invoice->due_date->translatedFormat('j F Y') }}</span>
                        <span class="font-semibold tabular-nums">
                            {{ Rupiah::format($invoice->balance_amount > 0 ? $invoice->balance_amount : $invoice->items_total_amount + $invoice->penalty_amount) }}
                        </span>
                    </div>
                </a>
            </li>
        @empty
            <li class="rounded-lg bg-white p-5 text-sm ring-1 ring-line">
                @if ($show === 'semua')
                    Belum ada tagihan untuk kontrak Anda.
                @else
                    Semua tagihan sudah lunas.
                    <button type="button" wire:click="$set('show', 'semua')" class="font-medium underline underline-offset-2">Lihat tagihan lama</button>
                @endif
            </li>
        @endforelse
    </ul>
</div>
