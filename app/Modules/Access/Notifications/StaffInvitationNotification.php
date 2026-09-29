<?php

namespace App\Modules\Access\Notifications;

use App\Modules\Access\Models\StaffInvitation;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * The invitation email (FR-USR-03). Sent right away rather than queued, so
 * the link's token never sits in the queue.
 */
final class StaffInvitationNotification extends Notification
{
    public function __construct(
        public readonly StaffInvitation $invitation,
        public readonly string $businessName,
        public readonly string $url,
        public readonly string $timezone,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Undangan bergabung dengan {$this->businessName} di Agentic Kost")
            ->greeting("Halo {$this->invitation->name},")
            ->line("Anda diundang sebagai {$this->invitation->role->getLabel()} di {$this->businessName}.")
            ->line('Buka tautan di bawah, lalu buat password untuk masuk.')
            ->action('Terima undangan', $this->url)
            ->line('Tautan berlaku sampai '.$this->invitation->expires_at->timezone($this->timezone)->translatedFormat('j F Y').'. Bila Anda tidak merasa diundang, abaikan email ini.');
    }
}
