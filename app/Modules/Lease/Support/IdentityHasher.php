<?php

namespace App\Modules\Lease\Support;

use RuntimeException;

/**
 * HMAC of an identity number, so a returning resident is recognised without
 * decrypting stored numbers (schema §1.9).
 */
final class IdentityHasher
{
    public static function hash(?string $identityNumber): ?string
    {
        $normalized = strtoupper((string) preg_replace('/[^A-Za-z0-9]/', '', (string) $identityNumber));

        if ($normalized === '') {
            return null;
        }

        $key = config('agentickost.identity_hash_key');

        if (! is_string($key) || $key === '') {
            throw new RuntimeException('IDENTITY_HASH_KEY belum diisi.');
        }

        return hash_hmac('sha256', $normalized, $key);
    }
}
