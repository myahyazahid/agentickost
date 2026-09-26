<?php

use App\Modules\Property\Enums\RentalPeriod;
use App\Support\Database\Check;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One invoice per contract (docs/adr/0008-keputusan-mvp.md), so the schema's
 * split_billing column is not created. The number is assigned on activation,
 * so drafts that are deleted leave no gap.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contracts', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignUlid('property_id')->constrained()->restrictOnDelete();
            $table->foreignUlid('room_id')->constrained()->restrictOnDelete();
            $table->foreignUlid('payer_id')->constrained()->restrictOnDelete();
            $table->string('number', 40)->nullable();
            $table->string('status', 32);
            $table->string('rental_period', 16);
            $table->bigInteger('rent_amount');
            $table->bigInteger('deposit_amount');
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->unsignedTinyInteger('billing_anchor_day');
            $table->date('next_period_start');
            $table->boolean('notify_resident')->default(true);
            $table->boolean('notify_payer')->default(true);
            $table->bigInteger('early_termination_penalty_amount')->nullable();
            $table->bigInteger('termination_penalty_amount')->nullable();
            $table->date('notice_given_on')->nullable();
            $table->date('planned_move_out_on')->nullable();
            $table->date('ended_on')->nullable();
            $table->text('termination_reason')->nullable();
            $table->foreignUlid('renewed_from_contract_id')->nullable()->constrained('contracts')->restrictOnDelete();
            $table->text('clauses')->nullable();
            $table->timestamp('end_reminder_sent_at')->nullable();
            $table->foreignUlid('created_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['tenant_id', 'number']);
            $table->index(['tenant_id', 'status', 'next_period_start']);
            $table->index(['tenant_id', 'room_id', 'status']);
        });

        Check::add('contracts', 'status_check', "status IN ('draft', 'active', 'notice', 'completed', 'terminated')");
        Check::enum('contracts', 'rental_period', RentalPeriod::class);
        Check::add('contracts', 'rent_amount_check', 'rent_amount > 0');
        Check::add('contracts', 'deposit_amount_check', 'deposit_amount >= 0');
        Check::add('contracts', 'anchor_day_check', 'billing_anchor_day BETWEEN 1 AND 31');
        Check::add('contracts', 'range_check', 'end_date IS NULL OR end_date >= start_date');
    }

    public function down(): void
    {
        Schema::dropIfExists('contracts');
    }
};
