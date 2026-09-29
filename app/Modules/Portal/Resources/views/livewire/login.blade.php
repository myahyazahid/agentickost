<div class="mx-auto max-w-sm pt-4 sm:pt-12">
    <h1 class="text-2xl font-semibold tracking-tight">Masuk ke portal penghuni</h1>
    <p class="mt-2 text-sm text-muted">Lihat tagihan, kirim bukti transfer, dan laporkan kerusakan kamar di {{ $portalTenant->name }}.</p>

    @if ($status)
        <p role="status" class="mt-5 rounded-md bg-white px-4 py-3 text-sm ring-1 ring-line">{{ $status }}</p>
    @endif

    @if (! $otpRequired)
        <p class="mt-5 rounded-md bg-amber-50 px-4 py-3 text-sm text-amber-900">
            Mode pengembangan: kode WhatsApp dimatikan (AGENTICKOST_PORTAL_OTP_REQUIRED=false), jadi nomor HP saja sudah cukup untuk masuk.
        </p>
    @endif

    @if ($sentTo === null)
        <form wire:submit="sendCode" class="mt-6 space-y-4">
            <div>
                <label for="phone" class="block text-sm font-medium">Nomor HP (WhatsApp)</label>
                <input
                    id="phone"
                    type="tel"
                    inputmode="tel"
                    autocomplete="tel"
                    wire:model="phone"
                    placeholder="0812 3456 7890"
                    required
                    @error('phone') aria-invalid="true" aria-describedby="phone-error" @enderror
                    class="mt-1 block min-h-11 w-full rounded-md border border-line bg-white px-3 text-base focus:border-(--accent) focus:outline-2 focus:outline-(--accent)"
                >
                @error('phone')
                    <p id="phone-error" class="mt-1 text-sm text-red-800">{{ $message }}</p>
                @enderror
                <p class="mt-1 text-sm text-muted">Nomor yang didaftarkan pengelola kost untuk Anda.</p>
            </div>

            <x-portal::button type="submit" class="w-full" wire:loading.attr="disabled" wire:target="sendCode">
                <span wire:loading.remove wire:target="sendCode">{{ $otpRequired ? 'Kirim kode lewat WhatsApp' : 'Masuk' }}</span>
                <span wire:loading wire:target="sendCode">Memproses...</span>
            </x-portal::button>
        </form>
    @else
        <form wire:submit="verify" class="mt-6 space-y-4">
            <p class="text-sm">
                Bila nomor <strong class="font-semibold">{{ $sentTo }}</strong> terdaftar, kode 6 angka sudah dikirim lewat WhatsApp. Kode berlaku 5 menit.
            </p>

            <div>
                <label for="code" class="block text-sm font-medium">Kode masuk</label>
                <input
                    id="code"
                    type="text"
                    inputmode="numeric"
                    autocomplete="one-time-code"
                    maxlength="6"
                    wire:model="code"
                    required
                    autofocus
                    @error('code') aria-invalid="true" aria-describedby="code-error" @enderror
                    class="mt-1 block min-h-11 w-full rounded-md border border-line bg-white px-3 text-lg tracking-[0.3em] tabular-nums focus:border-(--accent) focus:outline-2 focus:outline-(--accent)"
                >
                @error('code')
                    <p id="code-error" class="mt-1 text-sm text-red-800">{{ $message }}</p>
                @enderror
            </div>

            <x-portal::button type="submit" class="w-full" wire:loading.attr="disabled" wire:target="verify">
                <span wire:loading.remove wire:target="verify">Masuk</span>
                <span wire:loading wire:target="verify">Memeriksa kode...</span>
            </x-portal::button>

            <div class="flex flex-wrap gap-2">
                <x-portal::button variant="secondary" wire:click="sendCode" wire:loading.attr="disabled" wire:target="sendCode">Kirim ulang kode</x-portal::button>
                <x-portal::button variant="secondary" wire:click="useAnotherNumber">Ganti nomor</x-portal::button>
            </div>
        </form>
    @endif
</div>
