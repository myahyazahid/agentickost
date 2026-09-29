@php
    use App\Modules\Lease\Models\Resident;
    use App\Support\Money\Rupiah;

    $name = $account instanceof Resident ? $account->full_name : $account->name;
@endphp

<div class="space-y-6">
    <section>
        <h1 class="text-2xl font-semibold tracking-tight">{{ $name }}</h1>
        <p class="mt-1 text-sm text-muted">
            {{ $account->phone }}.
            {{ $portalAccess->isResident ? 'Masuk sebagai penghuni.' : 'Masuk sebagai pembayar: Anda melihat tagihan kamar yang Anda bayar.' }}
        </p>
    </section>

    <section aria-labelledby="contracts-heading">
        <h2 id="contracts-heading" class="font-semibold">Kontrak</h2>

        <ul class="mt-3 space-y-3">
            @foreach ($contracts as $contract)
                <li class="rounded-lg bg-white p-4 ring-1 ring-line">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="font-medium">Kamar {{ $contract->room?->number }}, {{ $contract->property?->name }}</p>
                            <p class="mt-0.5 text-sm text-muted">{{ $contract->number }}</p>
                        </div>
                        <x-portal::status :tone="$contract->status->isRunning() ? 'paid' : 'neutral'">{{ $contract->status->getLabel() }}</x-portal::status>
                    </div>

                    <dl class="mt-3 grid grid-cols-2 gap-x-4 gap-y-2 text-sm">
                        <div>
                            <dt class="text-muted">Mulai</dt>
                            <dd>{{ $contract->start_date->translatedFormat('j F Y') }}</dd>
                        </div>
                        <div>
                            <dt class="text-muted">Berakhir</dt>
                            <dd>{{ $contract->end_date?->translatedFormat('j F Y') ?? 'Sampai diakhiri' }}</dd>
                        </div>
                        <div>
                            <dt class="text-muted">Sewa</dt>
                            <dd class="tabular-nums">{{ Rupiah::format($contract->rent_amount) }} {{ mb_strtolower($contract->rental_period->getLabel()) }}</dd>
                        </div>
                        <div>
                            <dt class="text-muted">Deposit dipegang</dt>
                            <dd class="tabular-nums">{{ Rupiah::format($deposits[$contract->id] ?? 0) }}</dd>
                        </div>
                    </dl>

                    <x-portal::button variant="secondary" :href="route('portal.contracts.pdf', ['contract' => $contract->id])" target="_blank" rel="noopener" class="mt-3">
                        <x-heroicon-o-arrow-down-tray class="size-4" aria-hidden="true" />
                        Unduh kontrak
                    </x-portal::button>
                </li>
            @endforeach
        </ul>
    </section>

    <section class="space-y-2">
        @if ($portalAccess->isResident)
            <a href="{{ route('portal.announcements') }}" class="flex min-h-11 items-center gap-2 rounded-lg bg-white px-4 py-3 text-sm font-medium ring-1 ring-line hover:ring-(--accent) focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-(--accent)">
                <x-heroicon-o-megaphone class="size-5 text-muted" aria-hidden="true" />
                Pengumuman
            </a>
        @endif

        <form method="POST" action="{{ route('portal.logout') }}">
            @csrf
            <x-portal::button type="submit" variant="secondary" class="w-full">Keluar dari portal</x-portal::button>
        </form>
    </section>

    <p class="text-sm text-muted">
        Pasang portal ini di layar utama HP lewat menu browser, pilih "Tambahkan ke layar utama", supaya bisa dibuka seperti aplikasi.
    </p>
</div>
