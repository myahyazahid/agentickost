<?php

namespace Tests\Support;

use App\Support\Messaging\MessageChannel;

/**
 * Keeps sent messages in memory so tests can read them.
 */
final class FakeMessageChannel implements MessageChannel
{
    /**
     * @var list<array{phone: string, text: string}>
     */
    public array $sent = [];

    public static function install(): self
    {
        $channel = new self;
        app()->instance(MessageChannel::class, $channel);

        return $channel;
    }

    public function send(string $phone, string $text): void
    {
        $this->sent[] = ['phone' => $phone, 'text' => $text];
    }

    /**
     * The 6-digit code in the last message to the phone.
     */
    public function lastCode(string $phone): ?string
    {
        foreach (array_reverse($this->sent) as $message) {
            if ($message['phone'] === $phone && preg_match('/\b(\d{6})\b/', $message['text'], $match) === 1) {
                return $match[1];
            }
        }

        return null;
    }
}
