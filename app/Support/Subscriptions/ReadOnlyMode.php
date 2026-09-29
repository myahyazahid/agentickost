<?php

namespace App\Support\Subscriptions;

use RuntimeException;

/**
 * Thrown when a tenant in read-only mode tries to change data (FR-SUB-04,
 * PRD §9.6). Scheduled jobs skip such tenants quietly; the panel shows the
 * message as a notification.
 */
final class ReadOnlyMode extends RuntimeException
{
    public static function make(): self
    {
        return new self('Akun sedang dalam mode baca saja karena langganan belum dibayar. Data masih bisa dilihat dan diekspor. Bayar tagihan langganan di menu Langganan untuk mengubah data lagi.');
    }
}
