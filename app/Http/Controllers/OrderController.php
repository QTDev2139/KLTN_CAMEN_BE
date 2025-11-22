<?php

namespace App\Http\Controllers;

use App\Http\Resources\OrderResource;
use App\Models\Cart;
use App\Models\CartItem;
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
    // Quản lý đơn hàng tất cả khách hàng
    public function index()
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['message' => 'Vui lòng đăng nhập'], 407);
        }

        $orders = Order::with([
            'orderItems.product.product_images',
            'coupon',
        ])
            ->orderByDesc('id')
            ->get();

        return response()->json($orders);
    }

    public function show($id)
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['message' => 'Vui lòng đăng nhập'], 407);
        }

        $order = Order::with([
            'orderItems.product.product_images',
            'orderItems.product.product_translations',
            'coupon',
        ])->where('id', $id)->firstOrFail();

        return OrderResource::make($order);
    }
    // Người dùng Quản lý đơn hàng của mình
    public function userOrders($lang)
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['message' => 'Vui lòng đăng nhập'], 407);
        }

        $orders = Order::with([
            'orderItems.product.product_images',
            'orderItems.product.product_translations' => fn($q) => $q->whereRelation('language', 'code', $lang),
            'coupon',
            // chỉ load review của user hiện tại
            'orderItems.review' => fn($q) => $q->where('user_id', $user->id),
        ])
            ->where('user_id', $user->id)
            ->orderByDesc('id')
            ->get();

        return OrderResource::collection($orders);
    }
    
    /**
     * POST /api/orders
     * Tạo đơn hàng từ giỏ hiện tại (có thể đính kèm coupon_code)
     */
    public function store(Request $request)
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['message' => 'Vui lòng đăng nhập'], 407);
        }

        $validated = $request->validate([
            'note' => ['nullable', 'string', 'max:1000'],
            'ship_fee' => ['nullable', 'numeric'],
            'shipping_address' => ['nullable'], 
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

        // đảm bảo ship_fee có giá trị số
        $ship_fee = (float) ($validated['ship_fee'] ?? 0);

        // Tính subtotal từ cart items
        $subtotal = $cart->cartitems->sum(function (CartItem $ci) {
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
            // Use the query builder increment to avoid calling a protected model method
            Coupon::where('id', $coupon->id)->increment('used_count');
        }

        // Cộng phí vận chuyển vào grand total, đảm bảo không âm và làm tròn 2 chữ số
        $grandTotal = round(max($subtotal - $discountTotal + $ship_fee, 0), 2);

        // Tạo đơn + items trong transaction
        $order = DB::transaction(function () use ($user, $validated, $coupon, $subtotal, $discountTotal, $ship_fee, $grandTotal, $cart, $paymentMethod) {
            $code = 'CM' . now()->format('YmdHis') . strtoupper(Str::random(4));

            $order = Order::create([
                'code'            => $code,
                'status'          => 'pending',
                'payment_method'  => $paymentMethod,
                'payment_status'  => 'unpaid',
                'transaction_code' => null,
                'subtotal'        => $subtotal,
                'discount_total'  => $discountTotal,
                'ship_fee'       => $ship_fee,
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
            return response()->json(['message' => 'Vui lòng đăng nhập'], 407);
        }

        $validated = $request->validate([
            'status' => ['required', 'in:pending,processing,shipped,completed,cancelled,failed'],
            // 'note' => ['nullable', 'string', 'max:1000'],
        ]);

        $order = Order::findOrFail($id);

        if ($validated['status'] !== $order->status && $validated['status'] === 'completed') {
            $order->status = $validated['status'];
            $order->payment_status = 'paid';
            $order->save();
            return response()->json(['message' => 'Cập nhật đơn hàng thành công']);
        }
        if ($validated['status'] !== $order->status) {
            $order->status = $validated['status'];
            $order->save();
            return response()->json(['message' => 'Cập nhật đơn hàng thành công']);
        }
    }


    /**
     * DELETE /api/orders/{id}
     * Chỉ xóa đơn ở trạng thái pending và thuộc về user
     */
    public function destroy($id)
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['message' => 'Vui lòng đăng nhập'], 407);
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
