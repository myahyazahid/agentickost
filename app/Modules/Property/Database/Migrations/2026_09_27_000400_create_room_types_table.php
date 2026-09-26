<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('room_types', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignUlid('property_id')->constrained()->restrictOnDelete();
            $table->string('name', 80);
            $table->text('description')->nullable();
            $table->unsignedTinyInteger('default_capacity')->default(1);
            $table->json('facilities')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'property_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('room_types');
    }
};
