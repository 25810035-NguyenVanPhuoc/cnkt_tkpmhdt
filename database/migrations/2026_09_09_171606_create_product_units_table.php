<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('product_units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->foreignId('warehouse_id')->constrained('warehouses')->restrictOnDelete();
            $table->string('imei_serial')->nullable()->unique();
            $table->enum('status', ['in_stock', 'reserved', 'sold', 'returned', 'damaged'])->default('in_stock');
            $table->timestamps();
            // purchase_order_item_id / order_item_id (nullable FK) are added in
            // add_unit_refs_to_product_units_table, sau khi purchase_order_items
            // và order_items đã tồn tại (tránh phụ thuộc vòng lúc migrate).
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_units');
    }
};
