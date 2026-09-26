<?php

use App\Modules\Property\Enums\BillingMode;
use App\Modules\Property\Enums\PenaltyType;
use App\Modules\Property\Enums\ProrationBasis;
use App\Support\Database\Check;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('property_settings', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignUlid('property_id')->constrained()->restrictOnDelete();
            $table->string('billing_mode', 16);
            $table->unsignedTinyInteger('fixed_billing_day')->nullable();
            $table->unsignedTinyInteger('invoice_lead_days');
            $table->string('proration_basis', 16);
            $table->unsignedTinyInteger('grace_days');
            $table->string('penalty_type', 16);
            $table->bigInteger('penalty_amount')->nullable();
            $table->decimal('penalty_percent', 5, 2)->nullable();
            $table->bigInteger('penalty_max_amount')->nullable();
            $table->json('allocation_order');
            $table->unsignedSmallInteger('notice_days');
            $table->unsignedSmallInteger('booking_hold_hours');
            $table->json('cancellation_policy');
            $table->unsignedInteger('rounding_unit');
            $table->timestamps();

            $table->unique(['tenant_id', 'property_id']);
        });

        Check::enum('property_settings', 'billing_mode', BillingMode::class);
        Check::enum('property_settings', 'proration_basis', ProrationBasis::class);
        Check::enum('property_settings', 'penalty_type', PenaltyType::class);
        Check::add('property_settings', 'fixed_billing_day_check', 'fixed_billing_day IS NULL OR fixed_billing_day BETWEEN 1 AND 28');
        Check::add('property_settings', 'penalty_amount_check', 'penalty_amount IS NULL OR penalty_amount >= 0');
        Check::add('property_settings', 'penalty_max_amount_check', 'penalty_max_amount IS NULL OR penalty_max_amount >= 0');
        Check::add('property_settings', 'rounding_unit_check', 'rounding_unit >= 1');
    }

    public function down(): void
    {
        Schema::dropIfExists('property_settings');
    }
};
