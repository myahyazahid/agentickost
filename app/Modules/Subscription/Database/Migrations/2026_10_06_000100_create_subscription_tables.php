<?php

use App\Modules\Subscription\Enums\BillingCycle;
use App\Support\Database\Check;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Plans and subscriptions (schema §3.2 to §3.4, §13.4). Every tenant that
 * exists already gets a trial subscription; its trial end, if any, stays on
 * the tenant.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('code', 40)->unique();
            $table->string('name', 80);
            $table->text('description')->nullable();
            $table->bigInteger('monthly_price_amount');
            $table->bigInteger('yearly_price_amount')->nullable();
            $table->unsignedInteger('max_rooms')->nullable();
            $table->unsignedInteger('max_properties')->nullable();
            $table->unsignedInteger('max_staff')->nullable();
            $table->unsignedInteger('monthly_message_quota')->nullable();
            $table->unsignedInteger('monthly_ai_credit_quota')->nullable();
            $table->json('features');
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Check::add('plans', 'amounts_check', 'monthly_price_amount >= 0 AND (yearly_price_amount IS NULL OR yearly_price_amount >= 0)');

        Schema::create('subscriptions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignUlid('plan_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('status', 32);
            $table->string('billing_cycle', 16)->nullable();
            $table->date('current_period_start')->nullable();
            $table->date('current_period_end')->nullable();
            $table->timestamp('grace_ends_at')->nullable();
            $table->timestamp('read_only_since')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->index(['status']);
        });

        Check::add('subscriptions', 'status_check', "status IN ('trial', 'active', 'grace', 'read_only', 'frozen', 'cancelled')");
        Check::enum('subscriptions', 'billing_cycle', BillingCycle::class, nullable: true);

        Schema::create('subscription_invoices', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignUlid('subscription_id')->constrained()->restrictOnDelete();
            $table->foreignUlid('plan_id')->constrained()->restrictOnDelete();
            $table->string('number', 40)->unique();
            $table->string('billing_cycle', 16);
            $table->date('period_start');
            $table->date('period_end');
            $table->bigInteger('amount');
            $table->string('status', 32);
            $table->date('due_date');
            $table->timestamp('paid_at')->nullable();
            $table->string('gateway_reference', 100)->nullable();
            $table->string('payment_note', 255)->nullable();
            $table->foreignUlid('confirmed_by')->nullable()->constrained('platform_admins')->restrictOnDelete();
            $table->timestamp('voided_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
            $table->index(['status', 'due_date']);
        });

        Check::add('subscription_invoices', 'status_check', "status IN ('unpaid', 'paid', 'void')");
        Check::enum('subscription_invoices', 'billing_cycle', BillingCycle::class);
        Check::add('subscription_invoices', 'amount_check', 'amount >= 0');

        Schema::create('usage_counters', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->constrained()->restrictOnDelete();
            $table->string('metric', 32);
            $table->char('period', 7);
            $table->unsignedInteger('used')->default(0);
            $table->timestamps();

            $table->unique(['tenant_id', 'metric', 'period']);
        });

        Check::add('usage_counters', 'metric_check', "metric IN ('messages', 'ai_credits')");

        $now = now();

        foreach (DB::table('tenants')->pluck('id') as $tenantId) {
            DB::table('subscriptions')->insert([
                'id' => (string) Str::ulid(),
                'tenant_id' => $tenantId,
                'status' => 'trial',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('usage_counters');
        Schema::dropIfExists('subscription_invoices');
        Schema::dropIfExists('subscriptions');
        Schema::dropIfExists('plans');
    }
};
