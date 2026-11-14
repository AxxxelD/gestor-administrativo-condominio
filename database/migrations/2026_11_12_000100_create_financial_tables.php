<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('people', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('identity_number')->nullable()->unique();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->timestamps();
        });
        Schema::create('properties', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('occupancy_status')->default('vacant');
            $table->boolean('active')->default(true);
            $table->date('opening_date')->nullable();
            $table->timestamps();
        });
        Schema::create('occupancies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained()->restrictOnDelete();
            $table->foreignId('person_id')->constrained('people')->restrictOnDelete();
            $table->string('relationship');
            $table->date('starts_on');
            $table->date('ends_on')->nullable();
            $table->timestamps();
            $table->index(['property_id', 'ends_on']);
        });
        Schema::create('bank_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->bigInteger('opening_balance_bs_cents')->default(0);
            $table->date('opening_date')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });
        Schema::create('financial_periods', function (Blueprint $table) {
            $table->id();
            $table->string('month', 7)->unique();
            $table->string('status')->default('draft');
            $table->unsignedInteger('reserve_basis_points')->default(1000);
            $table->date('due_on')->nullable();
            $table->bigInteger('net_usd_cents')->default(0);
            $table->bigInteger('reserve_usd_cents')->default(0);
            $table->bigInteger('total_usd_cents')->default(0);
            $table->bigInteger('unit_charge_usd_cents')->default(0);
            $table->bigInteger('rounding_adjustment_usd_cents')->default(0);
            $table->unsignedInteger('units_count')->default(90);
            $table->json('items_snapshot')->nullable();
            $table->timestamp('issued_at')->nullable();
            $table->foreignId('issued_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });
        Schema::create('period_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('financial_period_id')->constrained()->restrictOnDelete();
            $table->string('category');
            $table->string('description');
            $table->bigInteger('amount_usd_cents');
            $table->boolean('is_provision')->default(false);
            $table->timestamps();
        });
        Schema::create('charges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained()->restrictOnDelete();
            $table->foreignId('financial_period_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('kind')->default('period');
            $table->string('description');
            $table->date('charged_on');
            $table->date('due_on')->nullable();
            $table->bigInteger('principal_usd_cents');
            $table->bigInteger('reserve_usd_cents')->default(0);
            $table->bigInteger('provision_usd_cents')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['property_id', 'financial_period_id']);
        });
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bank_account_id')->constrained()->restrictOnDelete();
            $table->date('paid_on');
            $table->bigInteger('amount_bs_cents');
            $table->bigInteger('rate_micros');
            $table->bigInteger('amount_usd_cents');
            $table->string('reference');
            $table->string('idempotency_key')->unique();
            $table->string('request_hash', 64);
            $table->string('status')->default('posted');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('voided_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('voided_at')->nullable();
            $table->text('void_reason')->nullable();
            $table->timestamps();
            $table->unique(['bank_account_id', 'paid_on', 'reference']);
        });
        Schema::create('property_credits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained()->restrictOnDelete();
            $table->foreignId('payment_id')->nullable()->constrained()->restrictOnDelete();
            $table->bigInteger('amount_usd_cents');
            $table->date('credited_on');
            $table->string('kind')->default('payment');
            $table->string('status')->default('active');
            $table->foreignId('created_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['payment_id', 'property_id']);
        });
        Schema::create('allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_credit_id')->constrained()->restrictOnDelete();
            $table->foreignId('charge_id')->constrained()->restrictOnDelete();
            $table->bigInteger('principal_usd_cents');
            $table->bigInteger('reserve_usd_cents');
            $table->bigInteger('provision_usd_cents')->default(0);
            $table->date('applied_on');
            $table->timestamp('voided_at')->nullable();
            $table->timestamps();
        });
        Schema::create('fund_openings', function (Blueprint $table) {
            $table->id();
            $table->string('type')->unique();
            $table->bigInteger('amount_usd_cents')->default(0);
            $table->date('date')->nullable();
            $table->foreignId('actor_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });
        Schema::create('fund_uses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('financial_period_id')->constrained()->restrictOnDelete();
            $table->foreignId('period_item_id')->unique()->constrained()->restrictOnDelete();
            $table->string('type')->default('provision');
            $table->bigInteger('amount_usd_cents');
            $table->date('used_on');
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });
        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bank_account_id')->constrained()->restrictOnDelete();
            $table->foreignId('period_item_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('description');
            $table->date('paid_on');
            $table->bigInteger('amount_bs_cents');
            $table->bigInteger('rate_micros');
            $table->bigInteger('amount_usd_cents');
            $table->string('fund')->default('operating');
            $table->string('idempotency_key')->unique();
            $table->string('request_hash', 64);
            $table->string('status')->default('posted');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('voided_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('voided_at')->nullable();
            $table->text('void_reason')->nullable();
            $table->timestamps();
        });
        DB::table('fund_openings')->insert([
            ['type' => 'reserve', 'amount_usd_cents' => 0],
            ['type' => 'provision', 'amount_usd_cents' => 0],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach (['expenses', 'fund_uses', 'fund_openings', 'allocations', 'property_credits', 'payments', 'charges', 'period_items', 'financial_periods', 'bank_accounts', 'occupancies', 'properties', 'people'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
