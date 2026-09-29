<div class="space-y-6">
    <a href="{{ route('portal.tickets') }}" class="inline-flex min-h-11 items-center gap-1 text-sm font-medium text-muted hover:text-ink">
        <x-heroicon-o-arrow-left class="size-4" aria-hidden="true" />
        Semua laporan
    </a>

    <section class="rounded-lg bg-white p-5 ring-1 ring-line">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <h1 class="min-w-0 text-xl font-semibold tracking-tight">{{ $ticket->title }}</h1>
            <x-portal::status :tone="$ticket->status->equals(\App\Modules\Maintenance\States\Ticket\Done::class) ? 'paid' : 'pending'">{{ $ticket->status->getLabel() }}</x-portal::status>
        </div>
        <p class="mt-1 text-sm text-muted">
            {{ $ticket->category->getLabel() }}, {{ $ticket->room ? "kamar {$ticket->room->number}" : 'area umum' }}{{ $photoCount > 0 ? ", {$photoCount} foto terlampir" : '' }}
        </p>
        <p class="mt-4 text-sm whitespace-pre-line">{{ $ticket->description }}</p>
    </section>

    <section aria-labelledby="progress-heading">
        <h2 id="progress-heading" class="font-semibold">Perkembangan</h2>
        <ol class="mt-3 space-y-3 border-s border-line ps-4">
            @foreach ($steps as $step)
                <li>
                    <p class="text-sm font-medium">{{ $step['label'] }}</p>
                    <p class="text-sm text-muted">{{ $step['at']->timezone($portalTenant->default_timezone)->translatedFormat('j M Y, H.i') }}</p>
                </li>
            @endforeach
        </ol>
    </section>
</div>
