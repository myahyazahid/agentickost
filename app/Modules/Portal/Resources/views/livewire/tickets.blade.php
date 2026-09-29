@php
    use App\Modules\Maintenance\States\Ticket\Done;
    use App\Modules\Maintenance\States\Ticket\Rejected;
@endphp

<div>
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h1 class="text-2xl font-semibold tracking-tight">Laporan kerusakan</h1>
        <x-portal::button :href="route('portal.tickets.create')">Laporkan kerusakan</x-portal::button>
    </div>

    <ul class="mt-4 space-y-3">
        @forelse ($tickets as $ticket)
            <li>
                <a
                    href="{{ route('portal.tickets.show', ['ticket' => $ticket->id]) }}"
                    class="block rounded-lg bg-white p-4 ring-1 ring-line hover:ring-(--accent) focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-(--accent)"
                >
                    <div class="flex items-start justify-between gap-3">
                        <p class="min-w-0 font-medium">{{ $ticket->title }}</p>
                        <x-portal::status :tone="$ticket->status->equals(Done::class) ? 'paid' : ($ticket->status->equals(Rejected::class) ? 'neutral' : 'pending')">
                            {{ $ticket->status->getLabel() }}
                        </x-portal::status>
                    </div>
                    <p class="mt-1 text-sm text-muted">
                        {{ $ticket->room ? "Kamar {$ticket->room->number}" : 'Area umum' }},
                        dilaporkan {{ $ticket->created_at->timezone($portalTenant->default_timezone)->translatedFormat('j M Y') }}
                    </p>
                </a>
            </li>
        @empty
            <li class="rounded-lg bg-white p-5 text-sm ring-1 ring-line">
                Belum ada laporan. Bila ada yang rusak di kamar atau area umum, laporkan dengan foto supaya pengelola bisa segera menanganinya.
            </li>
        @endforelse
    </ul>
</div>
