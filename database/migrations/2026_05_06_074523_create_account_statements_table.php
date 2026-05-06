<?php

use Emad\FeeCollection\Models\UpcomingPayment;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('account_statements', function (Blueprint $table) {
            $table->id();
            $table->morphs('accountable');
            $table->enum('type', \Emad\FeeCollection\Enums\AccountStatementType::values());
            $table->timestamp('date')->nullable();
            $table->string('number')->nullable();
            $table->text('description')->nullable();
            $table->decimal('amount', 8, 2)->nullable();
            $table->decimal('debit', 8, 2)->nullable();
            $table->decimal('credit', 8, 2)->nullable();
            $table->decimal('balance', 8, 2)->nullable();
            $table->string('document')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('account_statements');
    }
};
