<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('account_statements', function (Blueprint $table) {
            $table->string('type')->change();
            $table->foreignId('reference_id')->nullable()->after('document')->constrained('account_statements')->nullOnDelete();
            $table->string('status')->default('issued')->after('reference_id')->index();
            $table->timestamp('voided_at')->nullable()->after('status');
            $table->string('void_reason')->nullable()->after('voided_at');
        });
    }

    public function down(): void
    {
        Schema::table('account_statements', function (Blueprint $table) {
            $table->dropForeign(['reference_id']);
            $table->dropIndex(['status']);
            $table->dropColumn([
                'reference_id',
                'status',
                'voided_at',
                'void_reason',
            ]);
        });
    }
};
