<?php

use App\Modules\Finance\Enums\JournalEvent;
use App\Support\Database\Check;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Rebuilds the event constraint from JournalEvent, which gained
 * credit_refunded for check-out settlements (M1.6).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE journal_entries DROP CHECK journal_entries_event_check');
        Check::enum('journal_entries', 'event', JournalEvent::class);
    }

    public function down(): void
    {
        // The wider constraint stays; older values are a subset of it.
    }
};
