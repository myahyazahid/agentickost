<?php

use App\Modules\Lease\Enums\InspectionType;
use App\Modules\Lease\Enums\ItemCondition;
use App\Support\Database\Check;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * inspection_items.asset_id stays a plain column until assets exist (M2.2).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inspections', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignUlid('contract_id')->constrained()->restrictOnDelete();
            $table->foreignUlid('room_id')->constrained()->restrictOnDelete();
            $table->string('type', 16);
            $table->date('inspected_on');
            $table->foreignUlid('inspector_id')->constrained('users')->restrictOnDelete();
            $table->timestamp('resident_acknowledged_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'contract_id', 'type']);
        });

        Check::enum('inspections', 'type', InspectionType::class);

        Schema::create('inspection_items', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignUlid('inspection_id')->constrained()->cascadeOnDelete();
            $table->ulid('asset_id')->nullable();
            $table->string('item_name', 100);
            $table->string('condition', 16);
            $table->bigInteger('charge_amount')->default(0);
            $table->text('notes')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['tenant_id', 'inspection_id']);
        });

        Check::enum('inspection_items', 'condition', ItemCondition::class);
        Check::add('inspection_items', 'charge_amount_check', 'charge_amount >= 0');
    }

    public function down(): void
    {
        Schema::dropIfExists('inspection_items');
        Schema::dropIfExists('inspections');
    }
};
