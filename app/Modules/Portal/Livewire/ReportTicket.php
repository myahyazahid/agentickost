<?php

namespace App\Modules\Portal\Livewire;

use App\Modules\Documents\Enums\AttachmentCollection;
use App\Modules\Lease\Models\Contract;
use App\Modules\Maintenance\Actions\ReportTicketFromPortal;
use App\Modules\Maintenance\Enums\TicketCategory;
use App\Modules\Tenancy\Support\TenantStorage;
use App\Support\Subscriptions\ReadOnlyMode;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\WithFileUploads;

/**
 * A resident reports a problem in their room or a common area, with up to
 * three photos (FR-PRT-04).
 */
class ReportTicket extends PortalPage
{
    use WithFileUploads;

    public string $contractId = '';

    public string $place = 'room';

    public string $category = '';

    public string $title = '';

    public string $description = '';

    /**
     * @var array<int, UploadedFile>
     */
    public array $photos = [];

    public function mount(): void
    {
        abort_unless($this->access()->isResident, 404);

        $this->contractId = (string) $this->contracts()->first()?->id;
    }

    public function submit(TenantStorage $storage): void
    {
        $this->validate([
            'contractId' => ['required', Rule::in($this->access()->livingContractIds())],
            'place' => ['required', Rule::in(['room', 'common'])],
            'category' => ['required', Rule::enum(TicketCategory::class)],
            'title' => ['required', 'string', 'min:3', 'max:150'],
            'description' => ['required', 'string', 'min:5', 'max:2000'],
            'photos' => ['array', 'max:3'],
            'photos.*' => ['image', 'max:5120'],
        ], [
            'category.required' => 'Pilih jenis masalahnya.',
            'photos.max' => 'Paling banyak 3 foto.',
            'photos.*.image' => 'Foto harus berupa gambar (JPG, PNG, atau WEBP).',
            'photos.*.max' => 'Ukuran setiap foto paling besar 5 MB.',
        ]);

        $contract = $this->contracts()->firstWhere('id', $this->contractId) ?? abort(404);
        $paths = array_values(array_filter(array_map(
            fn (UploadedFile $photo): string|false => $photo->store($storage->path(AttachmentCollection::Before->directory())),
            $this->photos,
        )));

        try {
            $ticket = app(ReportTicketFromPortal::class)->handle($contract, [
                'category' => $this->category,
                'title' => $this->title,
                'description' => $this->description,
                'in_room' => $this->place === 'room',
                'photos' => $paths,
            ]);
        } catch (ValidationException $exception) {
            Storage::delete($paths);

            throw ValidationException::withMessages(collect($exception->errors())
                ->mapWithKeys(fn (array $messages, string $key): array => [str_starts_with($key, 'photos') ? 'photos' : ($key === 'in_room' ? 'place' : $key) => $messages])
                ->all());
        } catch (ReadOnlyMode) {
            Storage::delete($paths);

            throw ValidationException::withMessages(['description' => 'Portal sedang tidak menerima laporan. Hubungi pengelola kost langsung.']);
        }

        $this->redirectRoute('portal.tickets.show', ['ticket' => $ticket->id]);
    }

    public function render(): View
    {
        return $this->page('portal::livewire.report-ticket', 'Laporkan kerusakan', [
            'contracts' => $this->contracts(),
            'categories' => TicketCategory::cases(),
        ]);
    }

    /**
     * @return Collection<int, Contract>
     */
    private function contracts(): Collection
    {
        return Contract::query()
            ->whereIn('id', $this->access()->livingContractIds())
            ->with(['room', 'property'])
            ->orderByDesc('start_date')
            ->get();
    }
}
