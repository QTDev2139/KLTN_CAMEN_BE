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
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            // $table->decimal('base_price');
            $table->boolean('is_active')->default(true);
            $table->float('price');
            $table->float('compare_at_price'); // Giá khuyến mãi
            $table->integer('stock_quantity'); // số lượng tồn
            $table->string('origin')->default("Việt nam");
            $table->integer('quantity_per_pack'); // Số lượng mỗi combo
            $table->string('shipping_from')->default("TP. Hồ Chí Minh");
            $table->enum('type', ['domestic', 'export'])->default('domestic'); // Cột type chỉ có thể nhận 1 trong 2 giá trị đó

            $table->unsignedBigInteger('category_id');
            $table->foreign('category_id')->references('id')->on('categories')->onUpdate('cascade')->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
