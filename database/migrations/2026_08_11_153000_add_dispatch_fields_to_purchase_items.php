<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_items', function (Blueprint $table) {
            $table->string('dispatch_status', 20)->nullable()->after('received_quantity');
            $table->date('dispatched_at')->nullable()->after('dispatch_status');
            $table->decimal('dispatched_quantity', 12, 2)->nullable()->after('dispatched_at');
            $table->text('dispatch_remark')->nullable()->after('dispatched_quantity');
            $table->foreignId('alternative_product_id')
                ->nullable()
                ->after('dispatch_remark')
                ->constrained('products')
                ->nullOnDelete();
            $table->foreignId('original_product_id')
                ->nullable()
                ->after('alternative_product_id')
                ->constrained('products')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('purchase_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('alternative_product_id');
            $table->dropConstrainedForeignId('original_product_id');
            $table->dropColumn([
                'dispatch_status',
                'dispatched_at',
                'dispatched_quantity',
                'dispatch_remark',
            ]);
        });
    }
};
