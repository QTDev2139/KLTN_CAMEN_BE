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
        Schema::create('product_translations', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug');
            // $table->text('short_description');
            $table->text('description');
            $table->text('ingredient'); // Thành phần
            $table->text('nutrition_info'); // Giá trị dinh dưỡng
            $table->text('usage_instruction'); // HDSD
            $table->text('reason_to_choose'); // Lý do chọn sản phẩm

            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('language_id');
            $table->foreign('product_id')->references('id')->on('products')->onUpdate('cascade')->onDelete('cascade');
            $table->foreign('language_id')->references('id')->on('languages')->onUpdate('cascade')->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_translations');
    }
};
