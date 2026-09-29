<?php

namespace App\Modules\Subscription\Enums;

/**
 * What the daily subscription run did to a tenant, to tell its owner.
 */
enum SubscriptionEvent: string
{
    case RenewalIssued = 'renewal_issued';
    case GraceStarted = 'grace_started';
    case ReadOnlyStarted = 'read_only_started';
    case Frozen = 'frozen';

    public function title(): string
    {
        return match ($this) {
            self::RenewalIssued => 'Tagihan perpanjangan langganan terbit',
            self::GraceStarted => 'Langganan masuk masa tenggang',
            self::ReadOnlyStarted => 'Akun menjadi baca saja',
            self::Frozen => 'Akun dibekukan',
        };
    }

    public function body(): string
    {
        return match ($this) {
            self::RenewalIssued => 'Bayar sebelum periode berjalan berakhir supaya layanan tidak terputus.',
            self::GraceStarted => 'Masa langganan sudah habis. Data masih bisa diubah selama masa tenggang; setelah itu akun menjadi baca saja.',
            self::ReadOnlyStarted => 'Data masih bisa dilihat dan diekspor, tetapi tidak bisa diubah, dan tagihan penghuni tidak terbit otomatis sampai langganan dibayar.',
            self::Frozen => 'Login ditutup karena langganan tidak dibayar. Hubungi tim Agentic Kost untuk membukanya kembali.',
        };
    }
}
