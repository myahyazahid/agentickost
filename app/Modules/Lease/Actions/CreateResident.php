<?php

namespace App\Modules\Lease\Actions;

use App\Modules\Access\Models\User;
use App\Modules\Documents\Enums\AttachmentCollection;
use App\Modules\Documents\Support\AttachmentSync;
use App\Modules\Lease\Enums\Gender;
use App\Modules\Lease\Enums\IdentityType;
use App\Modules\Lease\Models\Resident;
use App\Modules\Lease\Support\IdentityHasher;
use App\Support\Actions\Action;
use App\Support\Actors\ActorContext;
use App\Support\Phone;
use Closure;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Registers a resident (FR-PNH-01). A resident whose identity number is
 * already on file is refused, so the existing record is reused.
 */
final class CreateResident extends Action
{
    public function __construct(
        private readonly AttachmentSync $attachments,
        private readonly ActorContext $actors,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     */
    public function handle(array $input): Resident
    {
        $this->authorize('create', Resident::class);

        $data = $this->validate(self::normalized($input), self::rules());

        if (($data['is_flagged'] ?? false) && ! $this->mayFlag()) {
            throw ValidationException::withMessages(['is_flagged' => 'Anda tidak berwenang memberi tanda pada penghuni.']);
        }

        self::ensureIdentityIsNew($data['identity_number'] ?? null);

        return $this->transaction(function () use ($data): Resident {
            $resident = Resident::create(Arr::except($data, 'identity_documents'));

            $this->attachments->sync($resident, AttachmentCollection::Identity, $data['identity_documents'] ?? [], 'identity_documents');

            return $resident;
        });
    }

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        $phone = fn (string $attribute, mixed $value, Closure $fail) => Phone::isValid(is_string($value) ? $value : null)
            ? null
            : $fail('Nomor telepon tidak valid. Contoh: 0812 3456 7890.');

        return [
            'full_name' => ['required', 'string', 'max:150'],
            'phone' => ['required', 'string', $phone],
            'email' => ['nullable', 'email', 'max:150'],
            'gender' => ['nullable', Rule::enum(Gender::class)],
            'birth_date' => ['nullable', 'date', 'before:today'],
            'identity_type' => ['nullable', 'required_with:identity_number', Rule::enum(IdentityType::class)],
            'identity_number' => ['nullable', 'string', 'max:50'],
            'institution' => ['nullable', 'string', 'max:150'],
            'emergency_contact_name' => ['nullable', 'string', 'max:100'],
            'emergency_contact_phone' => ['nullable', 'string', $phone],
            'emergency_contact_relation' => ['nullable', 'string', 'max:40'],
            'vehicle_plate' => ['nullable', 'string', 'max:20'],
            'internal_notes' => ['nullable', 'string'],
            'is_flagged' => ['boolean'],
            'identity_documents' => ['nullable', 'array', 'max:4'],
            'identity_documents.*' => ['string'],
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public static function normalized(array $input): array
    {
        foreach (['phone', 'emergency_contact_phone'] as $field) {
            if (array_key_exists($field, $input)) {
                $input[$field] = Phone::normalize(is_string($input[$field]) ? $input[$field] : null);
            }
        }

        if (isset($input['vehicle_plate']) && is_string($input['vehicle_plate'])) {
            $input['vehicle_plate'] = strtoupper(trim($input['vehicle_plate']));
        }

        return $input;
    }

    public static function ensureIdentityIsNew(mixed $identityNumber, ?Resident $ignore = null): void
    {
        $hash = IdentityHasher::hash(is_string($identityNumber) ? $identityNumber : null);

        if ($hash === null) {
            return;
        }

        $query = Resident::query()->where('identity_number_hash', $hash);

        if ($ignore !== null) {
            $query->whereKeyNot($ignore->id);
        }

        $existing = $query->first();

        if ($existing !== null) {
            throw ValidationException::withMessages([
                'identity_number' => "Nomor identitas ini sudah terdaftar atas nama {$existing->full_name}.",
            ]);
        }
    }

    private function mayFlag(): bool
    {
        $user = $this->actors->current()->user;

        return ! $user instanceof User || $user->can('resident.flag');
    }
}
