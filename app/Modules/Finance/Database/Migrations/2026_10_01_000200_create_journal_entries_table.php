<?php

use App\Modules\Finance\Enums\JournalEvent;
use App\Support\Database\Check;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Journal entries are never changed, so there is no updated_at.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('journal_entries', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignUlid('fiscal_period_id')->constrained()->restrictOnDelete();
            $table->foreignUlid('property_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('number', 40);
            $table->date('entry_date');
            $table->string('event', 40);
            $table->string('description', 255);
            $table->string('source_type', 80)->nullable();
            $table->ulid('source_id')->nullable();
            $table->foreignUlid('reversal_of_id')->nullable()->constrained('journal_entries')->restrictOnDelete();
            $table->string('created_by_type', 32);
            $table->ulid('created_by_id')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->unique(['tenant_id', 'number']);
            $table->unique(['tenant_id', 'reversal_of_id']);
            $table->index(['tenant_id', 'entry_date']);
            $table->index(['tenant_id', 'source_type', 'source_id']);
        });

        Check::enum('journal_entries', 'event', JournalEvent::class);
    }

    public function down(): void
    {
        Schema::dropIfExists('journal_entries');
    }
};
