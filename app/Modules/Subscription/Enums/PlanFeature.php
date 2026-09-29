<?php

namespace App\Modules\Subscription\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Features a plan can switch on or off (FR-SUB-02). Trials get every
 * feature. Features still in development are listed so plans can be set up
 * ahead; they take effect when the feature ships.
 */
enum PlanFeature: string implements HasLabel
{
    case AdvancedReports = 'advanced_reports';
    case ResidentPortal = 'resident_portal';
    case WhatsAppReminders = 'whatsapp_reminders';
    case PaymentGateway = 'payment_gateway';
    case AiAgents = 'ai_agents';

    public function getLabel(): string
    {
        return match ($this) {
            self::AdvancedReports => 'Laporan keuangan lengkap',
            self::ResidentPortal => 'Portal penghuni',
            self::WhatsAppReminders => 'Pengingat WhatsApp',
            self::PaymentGateway => 'Pembayaran online',
            self::AiAgents => 'Asisten AI',
        };
    }
}
