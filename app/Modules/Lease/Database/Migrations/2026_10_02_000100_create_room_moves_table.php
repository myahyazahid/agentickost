<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A move can credit more than one invoice (the period it falls in, and any
 * later period already issued), so credit notes point back at the move.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('room_moves', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignUlid('contract_id')->constrained()->restrictOnDelete();
            $table->foreignUlid('from_room_id')->constrained('rooms')->restrictOnDelete();
            $table->foreignUlid('to_room_id')->constrained('rooms')->restrictOnDelete();
            $table->date('moved_on');
            $table->bigInteger('old_rent_amount');
            $table->bigInteger('new_rent_amount');
            $table->bigInteger('deposit_difference_amount');
            $table->foreignUlid('invoice_id')->nullable()->constrained()->restrictOnDelete();
            $table->text('notes')->nullable();
            $table->foreignUlid('created_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['tenant_id', 'contract_id', 'moved_on']);
            $table->index(['tenant_id', 'to_room_id']);
        });

        Schema::table('credit_notes', function (Blueprint $table) {
            $table->foreignUlid('room_move_id')->nullable()->after('invoice_id')->constrained()->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('credit_notes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('room_move_id');
        });

        Schema::dropIfExists('room_moves');
    }
};
