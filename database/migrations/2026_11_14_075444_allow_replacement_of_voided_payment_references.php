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
        Schema::table('payments', function (Blueprint $table) {
            $table->index(['bank_account_id', 'paid_on', 'reference'], 'payments_reference_history');
            $table->dropUnique(['bank_account_id', 'paid_on', 'reference']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            // Las referencias repetidas de pagos anulados deben corregirse antes de revertir esta migración.
            $table->unique(['bank_account_id', 'paid_on', 'reference']);
            $table->dropIndex('payments_reference_history');
        });
    }
};
