<?php

namespace App\Modules\Lease\Actions;

use App\Modules\Documents\Enums\AttachmentCollection;
use App\Modules\Documents\Support\AttachmentSync;
use App\Modules\Lease\Models\Resident;
use App\Support\Actions\Action;
use Illuminate\Support\Arr;

/**
 * Updates a resident's profile. An empty identity number keeps the stored
 * one, because the form never shows the decrypted value. Changing the
 * "not recommended" flag needs its own permission (FR-PNH-06).
 */
final class UpdateResident extends Action
{
    public function __construct(private readonly AttachmentSync $attachments) {}

    /**
     * @param  array<string, mixed>  $input
     */
    public function handle(Resident $resident, array $input): Resident
    {
        $this->authorize('update', $resident);

        $data = $this->validate(CreateResident::normalized($input), CreateResident::rules());

        if (array_key_exists('is_flagged', $data) && (bool) $data['is_flagged'] !== $resident->is_flagged) {
            $this->authorize('flag', $resident);
        }

        if (blank($data['identity_number'] ?? null)) {
            unset($data['identity_number']);
        } else {
            CreateResident::ensureIdentityIsNew($data['identity_number'], $resident);
        }

        return $this->transaction(function () use ($resident, $data): Resident {
            $resident->update(Arr::except($data, 'identity_documents'));

            if (array_key_exists('identity_documents', $data)) {
                $this->attachments->sync($resident, AttachmentCollection::Identity, $data['identity_documents'] ?? [], 'identity_documents');
            }

            return $resident;
        });
    }
}
