<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plants', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->string('type')->default('plant');
            $table->text('address')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->default('plant_in_charge')->after('email');
            $table->foreignId('plant_id')->nullable()->after('role')->constrained()->nullOnDelete();
            $table->boolean('is_active')->default(true)->after('plant_id');
        });

        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('contact_person')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('gstin')->nullable();
            $table->text('address')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('sub_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['category_id', 'slug']);
        });

        Schema::create('brands', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->timestamps();
        });

        Schema::create('units', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('abbreviation');
            $table->timestamps();
        });

        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained();
            $table->foreignId('sub_category_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('brand_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('unit_id')->constrained();
            $table->string('name');
            $table->string('size', 50)->nullable();
            $table->string('sku')->unique();
            $table->unsignedInteger('reorder_level')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('product_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('requested_by')->constrained('users')->cascadeOnDelete();
            $table->string('requester_name');
            $table->string('status')->default('submitted');
            $table->text('remarks')->nullable();
            $table->text('decline_reason')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
            $table->index(['plant_id', 'status']);
        });

        Schema::create('product_request_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->string('item_name')->nullable();
            $table->string('size')->nullable();
            $table->foreignId('sub_category_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('quantity', 12, 2);
            $table->decimal('unit_price', 12, 2)->default(0);
            $table->decimal('gst_rate', 5, 2)->default(0);
            $table->text('billing_notes')->nullable();
            $table->text('remarks')->nullable();
            $table->string('image_path')->nullable();
            $table->timestamps();
        });

        Schema::create('purchase_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_request_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('plant_status')->default('submitted');
            $table->foreignId('plant_reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('plant_reviewed_at')->nullable();
            $table->text('plant_decline_reason')->nullable();
            $table->string('status')->default('submitted');
            $table->text('remarks')->nullable();
            $table->text('decline_reason')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
            $table->index(['plant_id', 'plant_status']);
            $table->index(['plant_id', 'status']);
        });

        Schema::create('purchase_request_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_request_item_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->string('item_name')->nullable();
            $table->string('size')->nullable();
            $table->foreignId('sub_category_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('quantity', 12, 2);
            $table->decimal('unit_price', 12, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('purchases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supplier_id')->constrained()->cascadeOnDelete();
            $table->foreignId('purchase_request_id')->nullable()->constrained()->nullOnDelete();
            $table->string('reference_no')->unique();
            $table->string('status')->default('ordered');
            $table->boolean('is_interstate')->default(false);
            $table->string('supplier_invoice_no')->nullable();
            $table->date('supplier_invoice_date')->nullable();
            $table->decimal('subtotal', 14, 2)->default(0);
            $table->decimal('total_cgst', 14, 2)->default(0);
            $table->decimal('total_sgst', 14, 2)->default(0);
            $table->decimal('total_igst', 14, 2)->default(0);
            $table->decimal('total_gst', 14, 2)->default(0);
            $table->decimal('grand_total', 14, 2)->default(0);
            $table->timestamp('ordered_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('received_at')->nullable();
            $table->foreignId('dispatched_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('dispatched_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['plant_id', 'status']);
        });

        Schema::create('purchase_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->decimal('quantity', 12, 2);
            $table->decimal('unit_price', 12, 2)->default(0);
            $table->decimal('gst_rate', 5, 2)->default(0);
            $table->decimal('taxable_amount', 14, 2)->default(0);
            $table->decimal('cgst_amount', 14, 2)->default(0);
            $table->decimal('sgst_amount', 14, 2)->default(0);
            $table->decimal('igst_amount', 14, 2)->default(0);
            $table->decimal('line_total', 14, 2)->default(0);
            $table->decimal('received_quantity', 12, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('stock_balances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->decimal('quantity', 12, 2)->default(0);
            $table->timestamps();
            $table->unique(['plant_id', 'product_id']);
        });

        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('sub_category_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type');
            $table->decimal('quantity', 12, 2);
            $table->string('reference_type');
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->foreignId('performed_by')->constrained('users')->cascadeOnDelete();
            $table->string('photo_path')->nullable();
            $table->boolean('photo_confirmed')->default(false);
            $table->text('reason')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('moved_at');
            $table->timestamps();
            $table->index(['plant_id', 'product_id']);
        });

        Schema::create('product_use_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sub_category_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('quantity', 12, 2);
            $table->decimal('life_hours', 12, 2)->default(0);
            $table->unsignedInteger('usage_count')->default(1);
            $table->timestamp('used_at');
            $table->foreignId('recorded_by')->constrained('users')->cascadeOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_use_entries');
        Schema::dropIfExists('stock_movements');
        Schema::dropIfExists('stock_balances');
        Schema::dropIfExists('purchase_items');
        Schema::dropIfExists('purchases');
        Schema::dropIfExists('purchase_request_items');
        Schema::dropIfExists('purchase_requests');
        Schema::dropIfExists('product_request_items');
        Schema::dropIfExists('product_requests');
        Schema::dropIfExists('products');
        Schema::dropIfExists('units');
        Schema::dropIfExists('brands');
        Schema::dropIfExists('sub_categories');
        Schema::dropIfExists('categories');
        Schema::dropIfExists('suppliers');
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('plant_id');
            $table->dropColumn(['role', 'is_active']);
        });
        Schema::dropIfExists('plants');
    }
};
