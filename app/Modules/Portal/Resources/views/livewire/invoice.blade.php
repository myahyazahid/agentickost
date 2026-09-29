@php
    use App\Support\Money\Rupiah;

    $open = $invoice->status->isOpen() && $invoice->balance_amount > 0;
@endphp

<div class="space-y-6">
    <a href="{{ route('portal.invoices') }}" class="inline-flex min-h-11 items-center gap-1 text-sm font-medium text-muted hover:text-ink">
        <x-heroicon-o-arrow-left class="size-4" aria-hidden="true" />
        Semua tagihan
    </a>

    <section class="rounded-lg bg-white p-5 ring-1 ring-line">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h1 class="text-xl font-semibold tracking-tight">
                    @if ($invoice->period_start)
                        Tagihan {{ $invoice->period_start->translatedFormat('j M') }} sampai {{ $invoice->period_end?->translatedFormat('j M Y') }}
                    @else
                        Tagihan {{ $invoice->number }}
                    @endif
                </h1>
                <p class="mt-1 text-sm text-muted">{{ $invoice->number }}, kamar {{ $invoice->contract?->room?->number }}, {{ $invoice->property?->name }}</p>
            </div>
            <x-portal::invoice-status :invoice="$invoice" />
        </div>

        <table class="mt-5 w-full text-sm">
            <caption class="sr-only">Rincian tagihan</caption>
            <tbody class="divide-y divide-line">
                @foreach ($invoice->items as $item)
                    <tr>
                        <td class="py-2 pe-3">{{ $item->description }}</td>
                        <td class="py-2 text-end tabular-nums">{{ Rupiah::format($item->amount) }}</td>
                    </tr>
                @endforeach
                @foreach ($penalties as $penalty)
                    <tr>
                        <td class="py-2 pe-3">Denda {{ $penalty->accrued_on->translatedFormat('j M Y') }}</td>
                        <td class="py-2 text-end tabular-nums">{{ Rupiah::format($penalty->amount) }}</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot class="border-t border-line">
                @if ($invoice->paid_amount > 0)
                    <tr>
                        <td class="pt-2 pe-3 text-muted">Sudah dibayar</td>
                        <td class="pt-2 text-end tabular-nums text-muted">{{ Rupiah::format($invoice->paid_amount) }}</td>
                    </tr>
                @endif
                @if ($invoice->credited_amount > 0)
                    <tr>
                        <td class="pt-2 pe-3 text-muted">Potongan</td>
                        <td class="pt-2 text-end tabular-nums text-muted">{{ Rupiah::format($invoice->credited_amount) }}</td>
                    </tr>
                @endif
                <tr>
                    <td class="pt-3 pe-3 font-semibold">Sisa</td>
                    <td class="pt-3 text-end text-lg font-semibold tabular-nums">{{ Rupiah::format($invoice->balance_amount) }}</td>
                </tr>
            </tfoot>
        </table>

        <p class="mt-4 text-sm text-muted">Jatuh tempo {{ $invoice->due_date->translatedFormat('l, j F Y') }}.</p>

        @if ($pdfUrl)
            <x-portal::button variant="secondary" :href="$pdfUrl" class="mt-4" target="_blank" rel="noopener">
                <x-heroicon-o-arrow-down-tray class="size-4" aria-hidden="true" />
                Unduh PDF
            </x-portal::button>
        @endif
    </section>

    @if ($pending->isNotEmpty())
        <section aria-labelledby="pending-heading" class="rounded-lg bg-amber-50 p-5 text-amber-900">
            <h2 id="pending-heading" class="font-semibold">Sedang diperiksa pengelola</h2>
            <ul class="mt-2 space-y-1 text-sm">
                @foreach ($pending as $payment)
                    <li>{{ Rupiah::format($payment->amount) }}, dikirim {{ $payment->paid_at->timezone($portalTenant->default_timezone)->translatedFormat('j M Y') }}</li>
                @endforeach
            </ul>
            <p class="mt-2 text-sm">Sisa tagihan berkurang setelah transfer dicocokkan dengan mutasi rekening.</p>
        </section>
    @endif

    @if ($open)
        <section aria-labelledby="pay-heading" class="rounded-lg bg-white p-5 ring-1 ring-line">
            <h2 id="pay-heading" class="text-lg font-semibold">Bayar lewat transfer</h2>

            @if ($accounts->isEmpty())
                <p class="mt-2 text-sm">Pengelola belum mencantumkan rekening tujuan. Tanyakan langsung ke pengelola kost cara membayarnya.</p>
            @else
                <ol class="mt-3 space-y-3 text-sm">
                    <li>
                        <p class="font-medium">1. Transfer ke salah satu rekening ini</p>
                        <ul class="mt-2 space-y-2">
                            @foreach ($accounts as $account)
                                <li class="rounded-md bg-canvas px-3 py-2">
                                    <span class="font-medium">{{ $account->provider_name }}</span>
                                    <span class="block text-base font-semibold tabular-nums select-all">{{ $account->account_number }}</span>
                                    <span class="text-muted">a.n. {{ $account->account_holder }}</span>
                                </li>
                            @endforeach
                        </ul>
                    </li>
                    <li><p class="font-medium">2. Kirim bukti transfer di bawah ini</p></li>
                </ol>

                @if ($sent)
                    <p role="status" class="mt-4 rounded-md bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                        Bukti transfer terkirim. Pengelola akan mencocokkannya dengan mutasi rekening, lalu tagihan ini diperbarui.
                    </p>
                @endif

                <form wire:submit="submitProof" class="mt-4 space-y-4">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label for="amount" class="block text-sm font-medium">Jumlah yang ditransfer</label>
                            <div class="mt-1 flex min-h-11 items-center rounded-md border border-line bg-white focus-within:border-(--accent) focus-within:outline-2 focus-within:outline-(--accent)">
                                <span class="ps-3 text-sm text-muted">Rp</span>
                                <input id="amount" type="text" inputmode="numeric" wire:model="amount" required class="min-h-11 w-full rounded-md bg-transparent px-2 text-base tabular-nums outline-none" @error('amount') aria-invalid="true" @enderror>
                            </div>
                            @error('amount') <p class="mt-1 text-sm text-red-800">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label for="paidOn" class="block text-sm font-medium">Tanggal transfer</label>
                            <input id="paidOn" type="date" wire:model="paidOn" required max="{{ now($portalTenant->default_timezone)->toDateString() }}" class="mt-1 block min-h-11 w-full rounded-md border border-line bg-white px-3 text-base focus:border-(--accent) focus:outline-2 focus:outline-(--accent)" @error('paidOn') aria-invalid="true" @enderror>
                            @error('paidOn') <p class="mt-1 text-sm text-red-800">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <div>
                        <label for="bankAccountId" class="block text-sm font-medium">Ke rekening</label>
                        <select id="bankAccountId" wire:model="bankAccountId" required class="mt-1 block min-h-11 w-full rounded-md border border-line bg-white px-3 text-base focus:border-(--accent) focus:outline-2 focus:outline-(--accent)">
                            @foreach ($accounts as $account)
                                <option value="{{ $account->id }}">{{ $account->provider_name }} {{ $account->account_number }}</option>
                            @endforeach
                        </select>
                        @error('bankAccountId') <p class="mt-1 text-sm text-red-800">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="proof" class="block text-sm font-medium">Foto bukti transfer</label>
                        <input id="proof" type="file" accept="image/jpeg,image/png,image/webp" wire:model="proof" class="mt-1 block w-full text-sm file:me-3 file:min-h-11 file:rounded-md file:border file:border-line file:bg-white file:px-4 file:font-medium" @error('proof') aria-invalid="true" @enderror>
                        <p wire:loading wire:target="proof" class="mt-1 text-sm text-muted">Mengunggah foto...</p>
                        @error('proof') <p class="mt-1 text-sm text-red-800">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="note" class="block text-sm font-medium">Catatan <span class="font-normal text-muted">(tidak wajib)</span></label>
                        <input id="note" type="text" wire:model="note" maxlength="100" placeholder="Misal: transfer dari rekening orang tua" class="mt-1 block min-h-11 w-full rounded-md border border-line bg-white px-3 text-base focus:border-(--accent) focus:outline-2 focus:outline-(--accent)">
                        @error('note') <p class="mt-1 text-sm text-red-800">{{ $message }}</p> @enderror
                    </div>

                    <x-portal::button type="submit" class="w-full sm:w-auto" wire:loading.attr="disabled" wire:target="submitProof,proof">
                        <span wire:loading.remove wire:target="submitProof">Kirim bukti transfer</span>
                        <span wire:loading wire:target="submitProof">Mengirim...</span>
                    </x-portal::button>
                </form>
            @endif
        </section>
    @endif
</div>
