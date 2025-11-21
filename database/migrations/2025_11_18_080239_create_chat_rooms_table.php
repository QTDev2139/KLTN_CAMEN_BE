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
        Schema::create('chat_rooms', function (Blueprint $table) {
            $table->id();
            // 1 khách hàng
            $table->unsignedBigInteger('customer_id');
            // 1 nhân viên bán hàng
            $table->unsignedBigInteger('staff_id')->nullable();

            // để load nhanh tin nhắn cuối
            $table->unsignedBigInteger('last_message_id')->nullable();
            $table->enum('status', ['pending', 'active', 'close'])->default('pending');
            $table->timestamps();

            $table->foreign('customer_id')
                ->references('id')->on('users')
                ->onDelete('cascade');

            $table->foreign('staff_id')
                ->references('id')->on('users')
                ->onDelete('cascade');

            $table->unique(['customer_id', 'staff_id']); 
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('chat_rooms');
    }
};
