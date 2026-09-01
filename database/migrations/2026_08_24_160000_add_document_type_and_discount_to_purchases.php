<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchases', function (Blueprint $table) {
            $table->string('document_type')->default('purchase_order')->after('purchase_request_id');
            $table->foreignId('quotation_id')->nullable()->after('document_type')->constrained('purchases')->nullOnDelete();
            $table->index(['document_type', 'plant_id']);
        });

        Schema::table('purchase_items', function (Blueprint $table) {
            $table->decimal('discount_percent', 5, 2)->default(0)->after('unit_price');
        });
    }

    public function down(): void
    {
        Schema::table('purchase_items', function (Blueprint $table) {
            $table->dropColumn('discount_percent');
        });

        Schema::table('purchases', function (Blueprint $table) {
            $table->dropConstrainedForeignId('quotation_id');
            $table->dropIndex(['document_type', 'plant_id']);
            $table->dropColumn('document_type');
        });
    }
};
