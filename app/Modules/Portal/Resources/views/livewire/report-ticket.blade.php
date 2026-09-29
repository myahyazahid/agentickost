<div>
    <a href="{{ route('portal.tickets') }}" class="inline-flex min-h-11 items-center gap-1 text-sm font-medium text-muted hover:text-ink">
        <x-heroicon-o-arrow-left class="size-4" aria-hidden="true" />
        Semua laporan
    </a>

    <h1 class="mt-2 text-2xl font-semibold tracking-tight">Laporkan kerusakan</h1>

    <form wire:submit="submit" class="mt-5 space-y-5 rounded-lg bg-white p-5 ring-1 ring-line">
        @if ($contracts->count() > 1)
            <div>
                <label for="contractId" class="block text-sm font-medium">Kamar</label>
                <select id="contractId" wire:model="contractId" class="mt-1 block min-h-11 w-full rounded-md border border-line bg-white px-3 text-base focus:border-(--accent) focus:outline-2 focus:outline-(--accent)">
                    @foreach ($contracts as $contract)
                        <option value="{{ $contract->id }}">Kamar {{ $contract->room?->number }}, {{ $contract->property?->name }}</option>
                    @endforeach
                </select>
            </div>
        @endif

        <fieldset>
            <legend class="text-sm font-medium">Di mana masalahnya?</legend>
            <div class="mt-2 grid gap-2 sm:grid-cols-2">
                @foreach (['room' => 'Di kamar saya', 'common' => 'Di area umum'] as $value => $label)
                    <label class="flex min-h-11 cursor-pointer items-center gap-3 rounded-md border border-line px-3 has-checked:border-(--accent) has-focus-visible:outline-2 has-focus-visible:outline-(--accent)">
                        <input type="radio" wire:model="place" value="{{ $value }}" class="size-4 accent-(--accent)">
                        <span class="text-sm">{{ $label }}</span>
                    </label>
                @endforeach
            </div>
            @error('place') <p class="mt-1 text-sm text-red-800">{{ $message }}</p> @enderror
        </fieldset>

        <div>
            <label for="category" class="block text-sm font-medium">Jenis masalah</label>
            <select id="category" wire:model="category" required class="mt-1 block min-h-11 w-full rounded-md border border-line bg-white px-3 text-base focus:border-(--accent) focus:outline-2 focus:outline-(--accent)" @error('category') aria-invalid="true" @enderror>
                <option value="">Pilih jenis masalah</option>
                @foreach ($categories as $category)
                    <option value="{{ $category->value }}">{{ $category->getLabel() }}</option>
                @endforeach
            </select>
            @error('category') <p class="mt-1 text-sm text-red-800">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="title" class="block text-sm font-medium">Singkatnya</label>
            <input id="title" type="text" wire:model="title" maxlength="150" required placeholder="Misal: keran kamar mandi bocor" class="mt-1 block min-h-11 w-full rounded-md border border-line bg-white px-3 text-base focus:border-(--accent) focus:outline-2 focus:outline-(--accent)" @error('title') aria-invalid="true" @enderror>
            @error('title') <p class="mt-1 text-sm text-red-800">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="description" class="block text-sm font-medium">Ceritakan masalahnya</label>
            <textarea id="description" wire:model="description" rows="4" maxlength="2000" required placeholder="Sejak kapan, seberapa parah, kapan Anda ada di kamar" class="mt-1 block w-full rounded-md border border-line bg-white px-3 py-2 text-base focus:border-(--accent) focus:outline-2 focus:outline-(--accent)" @error('description') aria-invalid="true" @enderror></textarea>
            @error('description') <p class="mt-1 text-sm text-red-800">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="photos" class="block text-sm font-medium">Foto <span class="font-normal text-muted">(tidak wajib, paling banyak 3)</span></label>
            <input id="photos" type="file" multiple accept="image/jpeg,image/png,image/webp" wire:model="photos" class="mt-1 block w-full text-sm file:me-3 file:min-h-11 file:rounded-md file:border file:border-line file:bg-white file:px-4 file:font-medium">
            <p wire:loading wire:target="photos" class="mt-1 text-sm text-muted">Mengunggah foto...</p>
            @error('photos') <p class="mt-1 text-sm text-red-800">{{ $message }}</p> @enderror
            @error('photos.*') <p class="mt-1 text-sm text-red-800">{{ $message }}</p> @enderror
        </div>

        <x-portal::button type="submit" class="w-full sm:w-auto" wire:loading.attr="disabled" wire:target="submit,photos">
            <span wire:loading.remove wire:target="submit">Kirim laporan</span>
            <span wire:loading wire:target="submit">Mengirim...</span>
        </x-portal::button>
    </form>
</div>
