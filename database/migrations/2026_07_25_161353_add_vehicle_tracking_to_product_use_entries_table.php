<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_use_entries', function (Blueprint $table) {
            $table->foreignId('vehicle_id')->nullable()->after('plant_id')->constrained()->nullOnDelete();
            $table->string('meter_type', 16)->default('hours')->after('sub_category_id');
            $table->decimal('current_reading', 12, 2)->nullable()->after('meter_type');
            $table->decimal('previous_reading', 12, 2)->nullable()->after('current_reading');
            $table->foreignId('replaced_product_id')->nullable()->after('product_id')->constrained('products')->nullOnDelete();

            $table->index(['vehicle_id', 'sub_category_id', 'used_at']);
        });
    }

    public function down(): void
    {
        Schema::table('product_use_entries', function (Blueprint $table) {
            $table->dropIndex(['vehicle_id', 'sub_category_id', 'used_at']);
            $table->dropConstrainedForeignId('replaced_product_id');
            $table->dropConstrainedForeignId('vehicle_id');
            $table->dropColumn(['meter_type', 'current_reading', 'previous_reading']);
        });
    }
};
