<?php

namespace App\Support\Messaging;

use Illuminate\Support\Facades\Log;

/**
 * Writes messages to the application log instead of sending them, for
 * local development and until a WhatsApp provider is chosen.
 */
final class LogMessageChannel implements MessageChannel
{
    public function send(string $phone, string $text): void
    {
        Log::info('Pesan WhatsApp (tidak dikirim, driver log)', ['phone' => $phone, 'text' => $text]);
    }
}
