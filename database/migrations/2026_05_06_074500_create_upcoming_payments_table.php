<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('upcoming_payments', function (Blueprint $table) {
            $table->id();
            $table->morphs('payable');
            $table->decimal('amount', 12, 2)->default(0);
            $table->decimal('remaining_amount', 12, 2)->default(0);
            $table->timestamp('due_date')->nullable();
            $table->foreignId('split_parent_id')->nullable()->index();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('upcoming_payments');
    }
};
