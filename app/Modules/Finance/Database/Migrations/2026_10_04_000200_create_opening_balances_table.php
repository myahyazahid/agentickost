<?php

use App\Modules\Finance\Enums\JournalEvent;
use App\Modules\Finance\Enums\OpeningBalanceKind;
use App\Support\Database\Check;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Opening balances entered at onboarding (FR-ONB-04, schema §10.7 and
 * §10.8). The journal event constraint is rebuilt for opening_balance.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('opening_balances', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->constrained()->restrictOnDelete();
            $table->date('cutoff_date');
            $table->string('status', 16);
            $table->foreignUlid('journal_entry_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignUlid('posted_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('posted_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
        });

        Check::add('opening_balances', 'status_check', "status IN ('draft', 'posted')");

        Schema::create('opening_balance_lines', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignUlid('opening_balance_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 16);
            $table->foreignUlid('contract_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignUlid('account_id')->nullable()->constrained()->restrictOnDelete();
            $table->bigInteger('amount');
            $table->string('note', 255)->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'opening_balance_id']);
            $table->index(['tenant_id', 'contract_id']);
        });

        Check::enum('opening_balance_lines', 'kind', OpeningBalanceKind::class);
        Check::add('opening_balance_lines', 'amount_check', 'amount > 0');
        Check::add(
            'opening_balance_lines',
            'target_check',
            "(kind = 'cash' AND account_id IS NOT NULL AND contract_id IS NULL) OR (kind <> 'cash' AND contract_id IS NOT NULL AND account_id IS NULL)",
        );

        DB::statement('ALTER TABLE journal_entries DROP CHECK journal_entries_event_check');
        Check::enum('journal_entries', 'event', JournalEvent::class);
    }

    public function down(): void
    {
        Schema::dropIfExists('opening_balance_lines');
        Schema::dropIfExists('opening_balances');
    }
};
