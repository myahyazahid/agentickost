<?php

use App\Modules\Maintenance\Enums\TicketCategory;
use App\Modules\Maintenance\Enums\TicketPriority;
use App\Support\Database\Check;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * asset_id, vendor_id, and maintenance_schedule_id stay plain columns until
 * assets, vendors, and schedules exist (M2.2). expenses.ticket_id gets its
 * foreign key here (schema §15).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tickets', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignUlid('property_id')->constrained()->restrictOnDelete();
            $table->foreignUlid('room_id')->nullable()->constrained()->restrictOnDelete();
            $table->ulid('asset_id')->nullable();
            $table->string('reported_by_type', 32);
            $table->ulid('reported_by_id')->nullable();
            $table->string('category', 32);
            $table->string('title', 150);
            $table->text('description');
            $table->string('priority', 16);
            $table->string('status', 32);
            $table->foreignUlid('assigned_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->ulid('vendor_id')->nullable();
            $table->timestamp('due_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->bigInteger('cost_amount')->default(0);
            $table->foreignUlid('paid_from_account_id')->nullable()->constrained('accounts')->restrictOnDelete();
            $table->boolean('charge_to_resident')->default(false);
            $table->foreignUlid('charge_invoice_id')->nullable()->constrained('invoices')->restrictOnDelete();
            $table->foreignUlid('expense_id')->nullable()->constrained()->restrictOnDelete();
            $table->ulid('maintenance_schedule_id')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'property_id', 'status']);
            $table->index(['tenant_id', 'assigned_user_id', 'status']);
            $table->index(['tenant_id', 'room_id']);
        });

        Check::enum('tickets', 'category', TicketCategory::class);
        Check::enum('tickets', 'priority', TicketPriority::class);
        Check::add('tickets', 'status_check', "status IN ('new', 'assigned', 'in_progress', 'awaiting_confirmation', 'done', 'rejected')");
        Check::add('tickets', 'cost_amount_check', 'cost_amount >= 0');
        Check::add('tickets', 'cost_source_check', 'cost_amount = 0 OR paid_from_account_id IS NOT NULL');

        Schema::create('ticket_updates', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignUlid('ticket_id')->constrained()->cascadeOnDelete();
            $table->string('actor_type', 32);
            $table->ulid('actor_id')->nullable();
            $table->string('from_status', 32)->nullable();
            $table->string('to_status', 32)->nullable();
            $table->text('note')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['tenant_id', 'ticket_id']);
        });

        Schema::table('expenses', function (Blueprint $table) {
            $table->foreign('ticket_id')->references('id')->on('tickets')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->dropForeign(['ticket_id']);
        });

        Schema::dropIfExists('ticket_updates');
        Schema::dropIfExists('tickets');
    }
};
