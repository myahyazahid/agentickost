<?php

use App\Support\Database\Check;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff_cash_handovers', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignUlid('property_id')->constrained()->restrictOnDelete();
            $table->foreignUlid('staff_user_id')->constrained('users')->restrictOnDelete();
            $table->bigInteger('expected_amount');
            $table->bigInteger('actual_amount');
            $table->bigInteger('difference_amount')->storedAs('actual_amount - expected_amount');
            $table->foreignUlid('destination_account_id')->constrained('accounts')->restrictOnDelete();
            $table->string('status', 16);
            $table->text('difference_note')->nullable();
            $table->text('dispute_note')->nullable();
            $table->timestamp('handed_over_at');
            $table->foreignUlid('confirmed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'staff_user_id', 'property_id']);
            $table->index(['tenant_id', 'status']);
        });

        Check::add('staff_cash_handovers', 'status_check', "status IN ('pending', 'confirmed', 'disputed')");
        Check::add('staff_cash_handovers', 'actual_amount_check', 'actual_amount >= 0');
        Check::add(
            'staff_cash_handovers',
            'difference_note_check',
            "status <> 'confirmed' OR actual_amount = expected_amount OR difference_note IS NOT NULL",
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_cash_handovers');
    }
};
