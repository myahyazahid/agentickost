<?php

use App\Modules\Property\Enums\GenderPolicy;
use App\Support\Timezone;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('properties', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->constrained()->restrictOnDelete();
            $table->string('name', 150);
            $table->string('code', 20);
            $table->text('address');
            $table->string('city', 80);
            $table->string('province', 80);
            $table->string('postal_code', 10)->nullable();
            $table->string('timezone', 40);
            $table->string('gender_policy', 16);
            $table->text('rules')->nullable();
            $table->json('facilities')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'code']);
        });

        $timezones = implode(', ', array_map(fn (Timezone $zone): string => "'{$zone->value}'", Timezone::cases()));
        $genderPolicies = implode(', ', array_map(fn (GenderPolicy $policy): string => "'{$policy->value}'", GenderPolicy::cases()));

        DB::statement("ALTER TABLE properties ADD CONSTRAINT properties_timezone_check CHECK (timezone IN ({$timezones}))");
        DB::statement("ALTER TABLE properties ADD CONSTRAINT properties_gender_policy_check CHECK (gender_policy IN ({$genderPolicies}))");
    }

    public function down(): void
    {
        Schema::dropIfExists('properties');
    }
};
