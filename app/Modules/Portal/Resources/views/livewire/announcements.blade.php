<div>
    <h1 class="text-2xl font-semibold tracking-tight">Pengumuman</h1>

    <div class="mt-4 space-y-3">
        @forelse ($announcements as $announcement)
            <article class="rounded-lg bg-white p-5 ring-1 ring-line">
                <h2 class="font-semibold">{{ $announcement->title }}</h2>
                <p class="mt-1 text-sm text-muted">
                    {{ $announcement->property?->name }},
                    {{ $announcement->published_at?->timezone($portalTenant->default_timezone)->translatedFormat('j F Y') }}
                </p>
                <p class="mt-3 text-sm whitespace-pre-line">{{ $announcement->body }}</p>
            </article>
        @empty
            <p class="rounded-lg bg-white p-5 text-sm ring-1 ring-line">Belum ada pengumuman dari pengelola.</p>
        @endforelse
    </div>
</div>
