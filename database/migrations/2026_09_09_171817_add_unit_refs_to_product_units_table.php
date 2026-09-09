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
        Schema::table('product_units', function (Blueprint $table) {
            $table->foreignId('purchase_order_item_id')->nullable()->after('warehouse_id')
                ->constrained('purchase_order_items')->nullOnDelete();
            $table->foreignId('order_item_id')->nullable()->after('purchase_order_item_id')
                ->constrained('order_items')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('product_units', function (Blueprint $table) {
            $table->dropConstrainedForeignId('order_item_id');
            $table->dropConstrainedForeignId('purchase_order_item_id');
        });
    }
};
