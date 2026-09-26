<?php

namespace App\Modules\Lease\Actions;

use App\Modules\Access\Audit\AuditLogger;
use App\Modules\Documents\Enums\AttachmentCollection;
use App\Modules\Documents\Models\Attachment;
use App\Modules\Lease\Models\Resident;
use App\Support\Actions\Action;

/**
 * Shows a resident's identity number and documents to an authorized role,
 * and records every look in the audit log (FR-PNH-02, NFR-PDP-04).
 */
final class RevealResidentIdentity extends Action
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @return array{identity_type: ?string, identity_number: ?string, documents: list<array{name: string, url: string}>}
     */
    public function handle(Resident $resident): array
    {
        $this->authorize('viewIdentity', $resident);

        return $this->transaction(function () use ($resident): array {
            $this->audit->record('resident.identity_viewed', $resident);

            return [
                'identity_type' => $resident->identity_type?->getLabel(),
                'identity_number' => $resident->identity_number,
                'documents' => array_values($resident->attachments()
                    ->where('collection', AttachmentCollection::Identity->value)
                    ->get()
                    ->map(fn (Attachment $document): array => [
                        'name' => $document->original_name,
                        'url' => $document->temporaryUrl(),
                    ])
                    ->all()),
            ];
        });
    }
}
