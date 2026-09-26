<?php

use App\Modules\Documents\Enums\DocumentType;
use App\Modules\Documents\Enums\ResetPeriod;
use App\Support\Database\Check;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_sequences', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->constrained()->restrictOnDelete();
            $table->string('document_type', 32);
            $table->string('format', 80);
            $table->string('reset_period', 16);
            $table->string('current_period_key', 10);
            $table->unsignedInteger('next_number');
            $table->timestamps();

            $table->unique(['tenant_id', 'document_type']);
        });

        Check::enum('document_sequences', 'document_type', DocumentType::class);
        Check::enum('document_sequences', 'reset_period', ResetPeriod::class);
    }

    public function down(): void
    {
        Schema::dropIfExists('document_sequences');
    }
};
