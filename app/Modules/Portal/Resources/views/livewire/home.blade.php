@php
    use App\Modules\Lease\Models\Resident;
    use App\Support\Money\Rupiah;

    $name = $account instanceof Resident ? $account->full_name : $account->name;
@endphp

<div class="space-y-6">
    <p class="text-sm text-muted">Halo, {{ $name }}</p>

    <section aria-labelledby="owed-heading" class="rounded-lg bg-white p-5 ring-1 ring-line">
        @if ($owed > 0)
            <h1 id="owed-heading" class="text-sm font-medium text-muted">Sisa tagihan</h1>
            <p class="mt-1 text-3xl font-semibold tracking-tight tabular-nums">{{ Rupiah::format($owed) }}</p>
            <p class="mt-2 text-sm">
                @if ($overdueCount > 0)
                    <span class="font-medium text-red-800">{{ $overdueCount }} tagihan sudah lewat jatuh tempo.</span>
                @else
                    Jatuh tempo berikutnya {{ $nextInvoice?->due_date->translatedFormat('j F Y') }}.
                @endif
            </p>
            <div class="mt-4 flex flex-wrap gap-2">
                <x-portal::button :href="route('portal.invoices.show', ['invoice' => $nextInvoice?->id])">Bayar tagihan {{ $nextInvoice?->due_date->translatedFormat('j M') }}</x-portal::button>
                <x-portal::button variant="secondary" :href="route('portal.invoices')">Semua tagihan</x-portal::button>
            </div>
        @else
            <h1 id="owed-heading" class="text-lg font-semibold">Tidak ada tagihan yang perlu dibayar</h1>
            <p class="mt-1 text-sm text-muted">Tagihan baru muncul di sini begitu diterbitkan pengelola.</p>
        @endif

        @if ($pendingPayments > 0)
            <p class="mt-4 border-t border-line pt-4 text-sm">
                {{ $pendingPayments }} bukti transfer sedang diperiksa pengelola.
                <a href="{{ route('portal.payments') }}" class="font-medium underline underline-offset-2">Lihat riwayat</a>
            </p>
        @endif
    </section>

    @if ($portalAccess->isResident)
        <section aria-labelledby="repair-heading" class="flex flex-wrap items-center justify-between gap-3 rounded-lg bg-white p-5 ring-1 ring-line">
            <div>
                <h2 id="repair-heading" class="font-semibold">Ada yang rusak?</h2>
                <p class="text-sm text-muted">
                    {{ $openTickets > 0 ? "{$openTickets} laporan Anda masih dikerjakan." : 'Laporkan dengan foto, pengelola langsung menerima.' }}
                </p>
            </div>
            <x-portal::button variant="secondary" :href="route('portal.tickets.create')">Laporkan kerusakan</x-portal::button>
        </section>

        <section aria-labelledby="news-heading">
            <div class="flex items-baseline justify-between gap-3">
                <h2 id="news-heading" class="font-semibold">Pengumuman</h2>
                @if ($announcements->isNotEmpty())
                    <a href="{{ route('portal.announcements') }}" class="inline-flex min-h-11 items-center text-sm font-medium underline underline-offset-2">Lihat semua</a>
                @endif
            </div>

            @forelse ($announcements as $announcement)
                <article class="mt-3 rounded-lg bg-white p-4 ring-1 ring-line">
                    <h3 class="font-medium">{{ $announcement->title }}</h3>
                    <p class="mt-1 text-xs text-muted">{{ $announcement->published_at?->timezone($portalTenant->default_timezone)->translatedFormat('j F Y') }}</p>
                    <p class="mt-2 line-clamp-3 text-sm whitespace-pre-line">{{ $announcement->body }}</p>
                </article>
            @empty
                <p class="mt-2 text-sm text-muted">Belum ada pengumuman dari pengelola.</p>
            @endforelse
        </section>
    @endif
</div>
