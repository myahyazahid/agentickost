<?php

use App\Modules\Lease\Enums\ShareType;
use App\Support\Database\Check;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contract_residents', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignUlid('contract_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('resident_id')->constrained()->restrictOnDelete();
            $table->boolean('is_primary')->default(false);
            $table->string('share_type', 16)->nullable();
            $table->bigInteger('share_amount')->nullable();
            $table->date('joined_on');
            $table->date('left_on')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'contract_id', 'resident_id']);
        });

        Check::enum('contract_residents', 'share_type', ShareType::class, nullable: true);
    }

    public function down(): void
    {
        Schema::dropIfExists('contract_residents');
    }
};
