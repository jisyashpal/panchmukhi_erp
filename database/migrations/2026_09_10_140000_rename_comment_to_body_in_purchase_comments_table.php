<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('purchase_comments', 'comment') && ! Schema::hasColumn('purchase_comments', 'body')) {
            Schema::table('purchase_comments', function (Blueprint $table) {
                $table->renameColumn('comment', 'body');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('purchase_comments', 'body') && ! Schema::hasColumn('purchase_comments', 'comment')) {
            Schema::table('purchase_comments', function (Blueprint $table) {
                $table->renameColumn('body', 'comment');
            });
        }
    }
};
