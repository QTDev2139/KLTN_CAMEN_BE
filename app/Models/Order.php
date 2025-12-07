<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Carbon\Carbon;

class Order extends Model
{
    protected $fillable = [
        'code',
        'status',
        'subtotal',
        'discount_total',
        'ship_fee',
        'grand_total',
        'payment_method',
        'payment_status',
        'payment_date',
        'transaction_code',
        'refund_amount',
        'refund_transaction_code',
        'img_refund',
        'shipping_address',
        'note',
        'user_id',
        'coupon_id',
    ];

    // casts chuyển đổi kiểu dữ liệu
    protected $casts = [
        'subtotal'       => 'decimal:2',
        'discount_total' => 'decimal:2',
        'ship_fee'      => 'decimal:2',
        'grand_total'    => 'decimal:2',
        'shipping_address' => 'array',
        'img_refund' => 'array',
    ];

    // n Orders -> 1 User
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    // n Orders -> 1 Coupon (có thể null)
    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class, 'coupon_id');
    }

    // 1 Order -> n OrderItems
    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class, 'order_id');
    }

    // Scope lọc theo khoảng ngày
    public function scopeFilterByDateRange(Builder $query, ?string $startDate = null, ?string $endDate = null): Builder
    {
        if ($startDate && $endDate) {
            $query->whereBetween('created_at', [
                Carbon::parse($startDate)->startOfDay(),
                Carbon::parse($endDate)->endOfDay(),
            ]);
        } elseif ($startDate) {
            $query->whereDate('created_at', '>=', $startDate);
        } elseif ($endDate) {
            $query->whereDate('created_at', '<=', $endDate);
        }

        return $query;
    }

    // Tổng doanh thu trong khoảng ngày
    public static function getTotalRevenueByRange(?string $startDate = null, ?string $endDate = null): float
    {
        return (float) self::query()->filterByDateRange($startDate, $endDate)
            ->sum('grand_total');
    }

    // Tổng doanh thu đã thanh toán trong khoảng ngày
    public static function getPaidRevenueByRange(?string $startDate = null, ?string $endDate = null): float
    {
        return (float) self::query()->filterByDateRange($startDate, $endDate)
            ->where('payment_status', 'paid')
            ->sum('grand_total');
    }

    // Tổng doanh thu chưa thanh toán trong khoảng ngày
    public static function getUnpaidRevenueByRange(?string $startDate = null, ?string $endDate = null): float
    {
        return (float) self::query()->filterByDateRange($startDate, $endDate)
            ->where('payment_status', '!=', 'paid')
            ->sum('grand_total');
    }

    // Tổng số đơn hàng trong khoảng ngày
    public static function getTotalOrdersByRange(?string $startDate = null, ?string $endDate = null): int
    {
        return self::query()->filterByDateRange($startDate, $endDate)
            ->count();
    }

    // Đơn hàng chờ xử lý trong khoảng ngày
    public static function getPendingOrdersByRange(?string $startDate = null, ?string $endDate = null): int
    {
        return self::query()->filterByDateRange($startDate, $endDate)
            ->where('status', 'pending')
            ->count();
    }
}
