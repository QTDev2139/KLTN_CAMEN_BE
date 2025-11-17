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
        Schema::create('coupons', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->enum('discount_type', ['percentage', 'fixed'])->default('fixed'); // Loại: phần trăm hoặc số tiền cố định
            $table->decimal('discount_value', 10, 2);
            $table->decimal('max_discount_amount', 10, 2)->nullable()->default(0); // percent: tối đa được giảm
            $table->decimal('min_order_amount', 10, 2)->nullable()->default(0); // đơn tối thiểu để áp dụng
            $table->integer('usage_limit')->nullable(); // số lần tối đa có thể dùng
            $table->integer('used_count')->default(0); // đã dùng bao nhiêu lần
            $table->dateTime('start_date')->nullable();
            $table->dateTime('end_date')->nullable();
            $table->enum('state', ['pending', 'approved', 'rejected'])->default('pending');
            $table->boolean('is_active')->default(false);
            $table->text('note')->nullable();
            $table->text('reason_end')->nullable();
            $table->unsignedBigInteger('user_id');
            $table->foreign('user_id')->references('id')->on('users')->onUpdate('cascade')->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('coupons');
    }
};
