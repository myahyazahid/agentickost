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
     * Directory under the tenant folder where uploads for this collection land.
     */
    public function directory(): string
    {
        return 'uploads/'.str_replace('_', '-', $this->value);
    }
}
