<?php

use Emad\FeeCollection\Models\AccountStatement;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('account_statement_upcoming_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(AccountStatement::class);
            $table->foreignIdFor(\Emad\FeeCollection\Models\UpcomingPayment::class);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('account_statement_upcoming_payments');
    }
};
