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
        Schema::create('quantity_deliveries', function (Blueprint $table) {
            $table->id();
            $table->integer('quantity');
            $table->integer('sent_qty');
            $table->integer('received_qty');
            $table->integer('shortage_qty');
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('delivery_id');
            $table->foreign('product_id')->references('id')->on('products')->onUpdate('cascade')->onDelete('cascade');
            $table->foreign('delivery_id')->references('id')->on('deliveries')->onUpdate('cascade')->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('quantity_deliveries');
    }
};
