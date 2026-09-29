<?php

namespace App\Support\Messaging;

/**
 * Sends a text message to a phone number (PRD §14.3). Modules depend on
 * this contract, so the WhatsApp provider can be swapped without touching
 * them.
 */
interface MessageChannel
{
    /**
     * @param  string  $phone  E.164, such as +6281234567890
     *
     * @throws MessageNotSent
     */
    public function send(string $phone, string $text): void;
}
