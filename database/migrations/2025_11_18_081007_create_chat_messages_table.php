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
        Schema::create('chat_messages', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('chat_room_id');
            $table->unsignedBigInteger('sender_id'); // user gửi (có thể là customer hoặc staff)

            $table->text('message');
            $table->json('images')->nullable();
            $table->timestamp('read_at')->nullable(); // thời điểm được đọc

            $table->timestamps();

            $table->foreign('chat_room_id')
                ->references('id')->on('chat_rooms')
                ->onDelete('cascade');

            $table->foreign('sender_id')
                ->references('id')->on('users')
                ->onDelete('cascade');

            $table->index(['chat_room_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('chat_messages');
    }
};
