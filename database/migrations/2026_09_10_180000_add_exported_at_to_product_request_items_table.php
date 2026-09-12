<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_request_items', function (Blueprint $table) {
            $table->timestamp('exported_at')->nullable()->after('image_path');
        });
    }

    public function down(): void
    {
        Schema::table('product_request_items', function (Blueprint $table) {
            $table->dropColumn('exported_at');
        });
    }
};
