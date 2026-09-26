<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attachments', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->constrained()->restrictOnDelete();
            $table->string('attachable_type', 80);
            $table->ulid('attachable_id');
            $table->string('collection', 32);
            $table->string('disk', 32);
            $table->string('path');
            $table->string('original_name');
            $table->string('mime_type', 100);
            $table->unsignedInteger('size_bytes');
            $table->boolean('is_encrypted')->default(false);
            $table->string('uploaded_by_type', 32);
            $table->ulid('uploaded_by_id')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'attachable_type', 'attachable_id', 'collection'], 'attachments_attachable_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attachments');
    }
};
