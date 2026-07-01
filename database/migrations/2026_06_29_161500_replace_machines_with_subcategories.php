<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->dropForeign(['machine_id']);
            $table->dropColumn('machine_id');
            $table->foreignId('sub_category_id')->nullable()->after('product_id')->constrained()->nullOnDelete();
        });

        Schema::table('product_use_entries', function (Blueprint $table) {
            $table->dropForeign(['machine_id']);
            $table->dropColumn('machine_id');
            $table->foreignId('sub_category_id')->nullable()->after('product_id')->constrained()->nullOnDelete();
        });

        Schema::dropIfExists('machines');
    }

    public function down(): void
    {
        // No down migration is needed since machines are fully removed.
    }
};
