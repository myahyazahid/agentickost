<?php

namespace App\Modules\Documents\Enums;

enum AttachmentCollection: string
{
    case Photo = 'photo';
    case PaymentProof = 'payment_proof';
    case Identity = 'identity';
    case Meter = 'meter';
    case Before = 'before';
    case After = 'after';
    case Document = 'document';
    case Inspection = 'inspection';

    /**
     * Identity documents are encrypted at rest (NFR-SEC-01).
     */
    public function isEncrypted(): bool
    {
        return $this === self::Identity;
    }

    /**
     * File types an upload may have. Photos are images; payment proofs are
     * shown as images; documents and identity papers may also be PDFs. SVG
     * and HTML are never accepted, since a browser would run their scripts.
     *
     * @return list<string>
     */
    public function acceptedMimeTypes(): array
    {
        $images = ['image/jpeg', 'image/png', 'image/webp'];

        return match ($this) {
            self::Document, self::Identity => [...$images, 'application/pdf'],
            default => $images,
        };
    }

    /**
     * Directory under the tenant folder where uploads for this collection land.
     */
    public function directory(): string
    {
        return 'uploads/'.str_replace('_', '-', $this->value);
    }
}
