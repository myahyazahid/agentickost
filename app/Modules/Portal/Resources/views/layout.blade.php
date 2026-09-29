@php
    use App\Modules\Portal\Support\PortalTheme;

    $tenant = $portalTenant;
    $account = $portalAccount ?? null;
    $access = $portalAccess ?? null;
    $nav = $account === null ? [] : array_values(array_filter([
        ['route' => 'portal.home', 'label' => 'Beranda', 'icon' => 'heroicon-o-home'],
        ['route' => 'portal.invoices', 'label' => 'Tagihan', 'icon' => 'heroicon-o-document-text', 'match' => 'portal.invoices*'],
        ['route' => 'portal.payments', 'label' => 'Riwayat', 'icon' => 'heroicon-o-clock'],
        $access?->isResident ? ['route' => 'portal.tickets', 'label' => 'Laporan', 'icon' => 'heroicon-o-wrench-screwdriver', 'match' => 'portal.tickets*'] : null,
        ['route' => 'portal.account', 'label' => 'Akun', 'icon' => 'heroicon-o-user-circle', 'match' => 'portal.account'],
    ]));
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>{{ $title ?? 'Portal' }} | {{ $tenant->name }}</title>
    <meta name="theme-color" content="{{ PortalTheme::accent($tenant) }}">
    <link rel="manifest" href="{{ route('portal.manifest') }}">
    <link rel="icon" type="image/png" href="{{ route('portal.icon', ['size' => 192]) }}">
    <link rel="apple-touch-icon" href="{{ route('portal.icon', ['size' => 192]) }}">
    @fonts
    @vite(['resources/css/app.css'])
</head>
<body
    class="min-h-dvh bg-canvas font-sans text-ink antialiased"
    style="--accent: {{ PortalTheme::accent($tenant) }}; --accent-text: {{ PortalTheme::accentText($tenant) }};"
>
    <header class="sticky top-0 z-20 border-b border-line bg-white">
        <div class="mx-auto flex max-w-3xl items-center gap-3 px-4 py-3">
            <a href="{{ $account ? route('portal.home') : route('portal.login') }}" class="flex min-h-11 min-w-0 items-center gap-3 rounded-md focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-(--accent)">
                @if ($tenant->logo_path)
                    <img src="{{ route('portal.logo') }}" alt="" class="h-8 w-auto max-w-28 object-contain">
                @endif
                <span class="truncate text-base font-semibold">{{ $tenant->name }}</span>
            </a>

            @if ($nav !== [])
                <nav aria-label="Menu utama" class="ms-auto hidden sm:block">
                    <ul class="flex items-center gap-1">
                        @foreach ($nav as $item)
                            @php($active = request()->routeIs($item['match'] ?? $item['route']))
                            <li>
                                <a
                                    href="{{ route($item['route']) }}"
                                    @if ($active) aria-current="page" @endif
                                    class="flex min-h-11 items-center rounded-md px-3 text-sm font-medium focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-(--accent) {{ $active ? 'bg-canvas text-ink' : 'text-muted hover:text-ink' }}"
                                >{{ $item['label'] }}</a>
                            </li>
                        @endforeach
                    </ul>
                </nav>
            @endif
        </div>
    </header>

    <main class="mx-auto max-w-3xl px-4 pt-5 {{ $nav !== [] ? 'pb-[calc(6rem+env(safe-area-inset-bottom))] sm:pb-12' : 'pb-12' }}">
        {{ $slot }}
    </main>

    @if ($nav !== [])
        <nav aria-label="Menu utama" class="fixed inset-x-0 bottom-0 z-20 border-t border-line bg-white pb-[env(safe-area-inset-bottom)] sm:hidden">
            <ul class="mx-auto grid max-w-md" style="grid-template-columns: repeat({{ count($nav) }}, minmax(0, 1fr));">
                @foreach ($nav as $item)
                    @php($active = request()->routeIs($item['match'] ?? $item['route']))
                    <li>
                        <a
                            href="{{ route($item['route']) }}"
                            @if ($active) aria-current="page" @endif
                            class="flex min-h-14 flex-col items-center justify-center gap-0.5 text-xs font-medium focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-(--accent) {{ $active ? 'text-ink' : 'text-muted' }}"
                        >
                            <x-dynamic-component :component="$item['icon']" @class(['size-6', 'text-(--accent)' => $active]) aria-hidden="true" />
                            {{ $item['label'] }}
                        </a>
                    </li>
                @endforeach
            </ul>
        </nav>
    @endif

    <script>
        if ('serviceWorker' in navigator) {
            navigator.serviceWorker.register(@js(route('portal.service-worker', absolute: false)), { scope: @js(route('portal.home', absolute: false).'/') });
        }
    </script>
</body>
</html>
