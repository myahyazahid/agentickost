<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('name', 150);
            $table->string('slug', 80)->unique();
            $table->string('logo_path')->nullable();
            $table->string('brand_color', 7)->nullable();
            $table->string('default_timezone', 40)->default('Asia/Jakarta');
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('frozen_at')->nullable();
            $table->json('settings');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenants');
    }
};
