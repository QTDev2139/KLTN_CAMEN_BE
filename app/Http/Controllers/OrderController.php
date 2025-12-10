<?php

namespace App\Http\Controllers;

use App\Http\Resources\OrderResource;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
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
            'orderItems.product.product_translations',
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
            'total_amount' => ['nullable', 'numeric'],
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
            $unit = $ci->unit_price ?? optional($ci->product)->price ?? 0;
            return $unit * (int)$ci->qty;
        });

        // Áp dụng coupon nếu có
        $coupon = null;
        $discountTotal = 0;

        if (!empty($validated['coupon_code'])) {
            $now = now()->setTimezone('Asia/Ho_Chi_Minh')->toDateTimeString();
            if ($now)
                $coupon = Coupon::query()
                    ->where('code', $validated['coupon_code'])
                    ->where('is_active', true)
                    ->when(true, function ($q) {
                        $q->where(function ($qq) {
                            $qq->whereNull('start_date')->orWhere('start_date', '<=', now()->setTimezone('Asia/Ho_Chi_Minh')->toDateTimeString());
                        })->where(function ($qq) {
                            $qq->whereNull('end_date')->orWhere('end_date', '>=', now()->setTimezone('Asia/Ho_Chi_Minh')->toDateTimeString());
                        });
                    })
                    ->first();

            if (!$coupon) {
                return response()->json(['message' => "Mã giảm giá không hợp lệ $now hoặc1 $coupon đã hết hạn"], 422);
            }

            if (!is_null($coupon->min_order_amount) && $subtotal < (float)$coupon->min_order_amount) {
                return response()->json(['message' => 'Đơn hàng không đạt giá trị tối thiểu để áp mã'], 422);
            }

            if ($coupon->discount_type === 'percent') {
                $discountTotal = round($subtotal * ((float)$coupon->discount_value / 100), 2);
            } else {
                $discountTotal = min((float)$coupon->discount_value, $subtotal);
            }
            Coupon::where('id', $coupon->id)->increment('used_count');
        }

        $grandTotal = round($validated['total_amount']);

        // --- KIỂM TRA STOCK TRƯỚC KHI TẠO ĐƠN ---
        $insufficient = [];
        foreach ($cart->cartitems as $ci) {
            $product = $ci->product;
            $qty = (int) $ci->qty;
            // nếu product tồn tại và có stock_quantity (không null) thì kiểm tra
            if ($product && !is_null($product->stock_quantity) && (int)$product->stock_quantity < $qty) {
                $insufficient[] = [
                    'product_id' => $product->id,
                    'available' => (int)$product->stock_quantity,
                    'requested' => $qty,
                ];
            }
        }
        if (!empty($insufficient)) {
            return response()->json([
                'message' => 'Số lượng trong kho không đủ cho một hoặc nhiều sản phẩm',
                'items' => $insufficient
            ], 422);
        }

        // Tạo đơn + items trong transaction (và cập nhật stock)
        try {
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
                    'ship_fee'        => $ship_fee,
                    'grand_total'     => $grandTotal,
                    'shipping_address' => $validated['shipping_address'] ?? null,
                    'note'            => $validated['note'] ?? null,
                    'user_id'         => $user->id,
                    'coupon_id'       => $coupon?->id,
                ]);

                // Tạo OrderItems từ cart items và cập nhật stock
                foreach ($cart->cartitems as $ci) {
                    $unit = $ci->unit_price ?? optional($ci->product)->price ?? 0;
                    OrderItem::create([
                        'order_id'   => $order->id,
                        'product_id' => $ci->product_id,
                        'qty'        => (int)$ci->qty,
                        'unit_price' => $unit,
                        'subtotal'   => $unit * (int)$ci->qty,
                    ]);

                    // Nếu product có stock tracked => giảm số lượng
                    // đảm bảo không giảm thành số âm: chỉ giảm khi stock >= qty
                    $updated = Product::where('id', $ci->product_id)
                        ->whereNotNull('stock_quantity')
                        ->where('stock_quantity', '>=', (int)$ci->qty)
                        ->decrement('stock_quantity', (int)$ci->qty);

                    if ($updated === 0 && !is_null(optional($ci->product)->stock_quantity)) {
                        // có thể xảy ra race condition: rollback transaction
                        throw new \Exception('Số lượng tồn kho không đủ cho product_id ' . $ci->product_id);
                    }
                }

                // Xóa giỏ hàng sau khi tạo đơn (nếu COD)
                if ($paymentMethod === 'cod') {
                    $cart->cartitems()->delete();
                    $cart->update(['is_active' => false]);
                }

                return $order;
            });
        } catch (\Exception $e) {
            return response()->json(['message' => 'Không thể tạo đơn: ' . $e->getMessage()], 422);
        }

        return response()->json([
            'message' => 'Tạo đơn hàng thành công',
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
            'status' => ['required', 'in:pending,processing,shipped,completed,cancelled,failed,refund_requested,refunded,partially_refunded'],
            // 'note' => ['nullable', 'string', 'max:1000'],
            'reason_refund' => ['nullable', 'string', 'max:1000'],
            'refund_amount' => ['nullable', 'numeric'],

        ]);

        $order = Order::findOrFail($id);

        $order->reason_refund = $order->reason_refund || '';

        $needs_saving = false;

        if ($validated['status'] !== $order->status) {
            $order->status = $validated['status'];
            $needs_saving = true;
            if ($order->status === 'completed') {
                $order->payment_status = 'paid';
            }
        }

        if (isset($validated['reason_refund'])) {
            if ($validated['reason_refund'] !== $order->reason_refund) {
                $order->reason_refund = $validated['reason_refund'];
                $needs_saving = true;
            }
        }

        if (isset($validated['refund_amount'])) {
            if ((float)$validated['refund_amount'] !== (float)$order->refund_amount) {
                $order->refund_amount = $validated['refund_amount'];
                $needs_saving = true;
            }
        }

        if ($needs_saving) {
            $order->save();
            return response()->json(['message' => 'Cập nhật đơn hàng thành công']);
        }

        return response()->json(['message' => 'Cập nhật thất bại'], 200);
    }

    public function refundRequest(Request $request)
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['message' => 'Vui lòng đăng nhập'], 407);
        }

        $validated = $request->validate([
            'order_code'    => ['required', 'string'],
            'reason_refund' => ['nullable', 'string', 'max:1000'],
            'images'        => ['nullable', 'array'],
            'images.*'      => ['file', 'mimes:jpg,jpeg,png,gif,webp', 'max:5120'],
        ]);

        $order = Order::where('code', $validated['order_code'])->first();
        if (!$order) {
            return response()->json(['message' => 'Đơn hàng không tồn tại'], 404);
        }

        // Nếu user không phải chủ đơn (hoặc admin) thì từ chối
        if ($order->user_id != $user->id) {
            return response()->json(['message' => 'Không có quyền thao tác đơn này'], 403);
        }

        // Prepare existing images (Order->img_refund is cast to array in model)
        $existingImgs = is_array($order->img_refund) ? $order->img_refund : [];

        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $file) {
                $file_name = time() . '_' . $file->getClientOriginalName();
                $path = $file->storeAs('refunds', $file_name, 'public');
                $existingImgs[] = $path;
            }
        }

        $order->reason_refund = $validated['reason_refund'] ?? $order->reason_refund;
        if (!empty($existingImgs)) {
            $order->img_refund = $existingImgs;
        }
        $order->status = 'refund_requested';
        $order->save();

        return response()->json([
            'message' => 'Yêu cầu hoàn tiền đã được gửi',
            'data' => $order->load(['orderItems.product.product_images', 'coupon']),
        ], 200);
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
