<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('expense_templates', function (Blueprint $table) {
            $table->id();
            $table->string('description');
            $table->string('category');
            $table->bigInteger('amount_usd_cents');
            $table->boolean('recurring')->default(true);
            $table->boolean('active')->default(true);
            $table->boolean('is_provision')->default(false);
            $table->timestamps();
        });
        Schema::table('period_items', function (Blueprint $table) {
            $table->foreignId('expense_template_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('attachment_path')->nullable();
            $table->string('attachment_name')->nullable();
            $table->date('service_date')->nullable();
            $table->unique(['financial_period_id', 'expense_template_id']);
        });
        Schema::create('exchange_rates', function (Blueprint $table) {
            $table->id();
            $table->date('date')->unique();
            $table->unsignedBigInteger('rate_micros');
            $table->string('source');
            $table->date('reference_date');
            $table->timestamp('fetched_at')->nullable();
            $table->string('reason')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->timestamps();
        });
        Schema::table('charges', function (Blueprint $table) {
            $table->json('document_snapshot')->nullable();
        });
        foreach (['payments', 'expenses'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->string('rate_source')->nullable();
                $table->date('rate_reference_date')->nullable();
            });
        }
        Schema::table('expenses', function (Blueprint $table) {
            $table->string('reference')->nullable();
        });
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->json('value');
            $table->timestamps();
        });
        Schema::create('deliveries', function (Blueprint $table) {
            $table->id();
            $table->string('recipient');
            $table->string('subject');
            $table->text('body');
            $table->string('kind');
            $table->foreignId('charge_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('status')->default('preview');
            $table->text('error')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
        });
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users');
            $table->string('action');
            $table->string('entity');
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->json('details')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('deliveries');
        Schema::dropIfExists('settings');
        Schema::dropIfExists('exchange_rates');
        Schema::table('charges', function (Blueprint $table) {
            $table->dropColumn('document_snapshot');
        });
        foreach (['payments', 'expenses'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->dropColumn(['rate_source', 'rate_reference_date']);
            });
        }
        Schema::table('expenses', function (Blueprint $table) {
            $table->dropColumn('reference');
        });
        Schema::table('period_items', function (Blueprint $table) {
            $table->dropUnique(['financial_period_id', 'expense_template_id']);
            $table->dropConstrainedForeignId('expense_template_id');
            $table->dropColumn(['attachment_path', 'attachment_name', 'service_date']);
        });
        Schema::dropIfExists('expense_templates');
    }
};
