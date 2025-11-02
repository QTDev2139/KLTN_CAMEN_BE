<?php

namespace App\Http\Controllers;

use App\Models\Cart;
use App\Models\Cartitem;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class OrderController extends Controller
{
    /**
     * GET /api/orders
     * Danh sách đơn của user đăng nhập (mới nhất trước)
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $perPage = (int)($request->query('per_page', 10));
        $orders = Order::with([
            'orderItems.product.product_images',
            'coupon',
        ])
            ->where('user_id', $user->id)
            ->orderByDesc('id')
            ->paginate($perPage);

        return response()->json([
            'message' => 'Lấy danh sách đơn hàng thành công',
            'data' => $orders,
        ]);
    }

    /**
     * POST /api/orders
     * Tạo đơn hàng từ giỏ hiện tại (có thể đính kèm coupon_code)
     */
    public function store(Request $request)
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $validated = $request->validate([
            'note' => ['nullable', 'string', 'max:1000'],
            'shipping_address' => ['nullable'], // có thể là JSON trên FE; lưu dạng text/json tùy migration
            'coupon_code' => ['nullable', 'string', 'max:100'],
            'payment_method'    => ['nullable', Rule::in(['cod', 'vnpay', 'momo'])],
        ]);
        $paymentMethod = $validated['payment_method'] ?? 'cod';

        // Lấy giỏ active của user
        $cart = Cart::with(['cartitems.product'])
            ->where('user_id', $user->id)
            ->first();

        if (!$cart || $cart->cartitems->isEmpty()) {
            return response()->json(['message' => 'Giỏ hàng trống'], 422);
        }

        // Tính subtotal từ cart items
        $subtotal = $cart->cartitems->sum(function (Cartitem $ci) {
            // ưu tiên unit_price trên cart item; fallback sang product->price nếu thiếu
            $unit = $ci->unit_price ?? optional($ci->product)->price ?? 0;
            return $unit * (int)$ci->qty;
        });

        // Áp dụng coupon nếu có
        $coupon = null;
        $discountTotal = 0;

        if (!empty($validated['coupon_code'])) {
            $coupon = Coupon::query()
                ->where('code', $validated['coupon_code'])
                ->where('is_active', true)
                ->when(true, function ($q) {
                    // ngày hiệu lực (nếu có trong DB)
                    $q->where(function ($qq) {
                        $qq->whereNull('start_date')->orWhere('start_date', '<=', now());
                    })->where(function ($qq) {
                        $qq->whereNull('end_date')->orWhere('end_date', '>=', now());
                    });
                })
                ->first();

            if (!$coupon) {
                return response()->json(['message' => 'Mã giảm giá không hợp lệ hoặc đã hết hạn'], 422);
            }

            if (!is_null($coupon->min_order_amount) && $subtotal < (float)$coupon->min_order_amount) {
                return response()->json(['message' => 'Đơn hàng không đạt giá trị tối thiểu để áp mã'], 422);
            }

            // Tính giảm giá
            if ($coupon->discount_type === 'percent') {
                $discountTotal = round($subtotal * ((float)$coupon->discount_value / 100), 2);
            } else {
                $discountTotal = min((float)$coupon->discount_value, $subtotal);
            }
        }

        $grandTotal = max($subtotal - $discountTotal, 0);

        // Tạo đơn + items trong transaction
        $order = DB::transaction(function () use ($user, $validated, $coupon, $subtotal, $discountTotal, $grandTotal, $cart, $paymentMethod) {
            $code = 'ORD' . now()->format('YmdHis') . strtoupper(Str::random(4));

            $order = Order::create([
                'code'            => $code,
                'status'          => 'pending',
                'payment_method'  => $paymentMethod,
                'payment_status'  => 'unpaid', // ✅ Mặc định unpaid
                'transaction_code' => null, // ✅ Null khi tạo, cập nhật sau khi thanh toán
                'subtotal'        => $subtotal,
                'discount_total'  => $discountTotal,
                'grand_total'     => $grandTotal,
                'shipping_address' => $validated['shipping_address'] ?? null,
                'note'            => $validated['note'] ?? null,
                'user_id'         => $user->id,
                'coupon_id'       => $coupon?->id,
            ]);

            // Tạo OrderItems từ cart items
            foreach ($cart->cartitems as $ci) {
                $unit = $ci->unit_price ?? optional($ci->product)->price ?? 0;
                OrderItem::create([
                    'order_id'   => $order->id,
                    'product_id' => $ci->product_id,
                    'qty'        => (int)$ci->qty,
                    'unit_price' => $unit,
                    'subtotal'   => $unit * (int)$ci->qty,
                ]);
            }

            // Xóa giỏ hàng sau khi tạo đơn
            if ($paymentMethod === 'cod') {
                $cart->cartitems()->delete();
                $cart->update(['is_active' => false]);
            }

            return $order;
        });

        return response()->json([
            'message' => 'Tạo đơn hàng thành công',
            'data' => $order->load(['orderItems.product.product_images', 'coupon']),
        ], 201);
    }

    /**
     * PUT/PATCH /api/orders/{id}
     * Cập nhật trạng thái đơn (user chỉ được hủy đơn pending)
     */
    public function update(Request $request, $id)
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $validated = $request->validate([
            'status' => ['required', 'in:pending,processing,paid,completed,cancelled,failed'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        $order = Order::where('id', $id)
            ->where('user_id', $user->id)
            ->firstOrFail();

        // Rule đơn giản: user chỉ được chuyển từ pending -> cancelled hoặc cập nhật note
        if ($validated['status'] !== $order->status) {
            if ($order->status !== 'pending' || $validated['status'] !== 'cancelled') {
                return response()->json(['message' => 'Không thể cập nhật trạng thái đơn này'], 422);
            }
            $order->status = 'cancelled';
            $order->payment_status = $order->payment_status === 'paid' ? 'paid' : 'failed';
        }

        if (array_key_exists('note', $validated)) {
            $order->note = $validated['note'];
        }

        $order->save();

        return response()->json([
            'message' => 'Cập nhật đơn hàng thành công',
            'data' => $order->fresh()->load(['orderItems.product.product_images', 'coupon']),
        ]);
    }

    /**
     * DELETE /api/orders/{id}
     * Chỉ xóa đơn ở trạng thái pending và thuộc về user
     */
    public function destroy($id)
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $order = Order::where('id', $id)
            ->where('user_id', $user->id)
            ->firstOrFail();

        if ($order->status !== 'pending') {
            return response()->json(['message' => 'Chỉ có thể xóa đơn ở trạng thái pending'], 422);
        }

        DB::transaction(function () use ($order) {
            $order->orderItems()->delete();
            $order->delete();
        });

        return response()->json(['message' => 'Xóa đơn hàng thành công'], 200);
    }
}
