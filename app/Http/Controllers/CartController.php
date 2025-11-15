<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCartRequest;
use App\Http\Resources\CartResource;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CartController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index($lang)
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['message' => 'Vui lòng đăng nhập để xem giỏ hàng'], 401);
        }

        $cart = Cart::with([
            'cartitems.product.product_translations' => fn($language) => $language->whereRelation('language', 'code', $lang),
            'cartitems.product.product_images'
        ])
            ->where('user_id', $user->id)
            ->first();
        if (!$cart) {
            return response()->json(['message' => 'Giỏ hàng trống'], 200);
        }
        // FIX: Dùng make() thay vì collection()
        return CartResource::make($cart);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreCartRequest $request)
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['message' => 'Vui lòng đăng nhập trước khi thêm sản phẩm vào giỏ hàng'], 401);
        }

        // Tìm or tạo giỏ hàng cho người dùng
        $cart = Cart::firstOrCreate(
            ['user_id' => $user->id]
        );

        // Tìm sản phẩm
        $product = Product::findOrFail($request->product_id);

        // Kiểm tra tồn kho
        if ($product->stock_quantity < $request->qty) {
            return response()->json(['message' => 'Số lượng sản phẩm trong kho không đủ'], 400);
        }

        // Kiểm tra sản phẩm đã có trong giỏ hàng chưa
        $existingCartItem = CartItem::where('cart_id', $cart->id)
            ->where('product_id', $product->id)
            ->first();

        if ($existingCartItem) {
            // Cập nhật số lượng sản phẩm trong giỏ hàng
            $existingCartItem->qty += $request->qty;
            $existingCartItem->subtotal = $existingCartItem->unit_price * $existingCartItem->qty;
            $existingCartItem->save();
        } else {
            // Nếu chưa có thì thêm mới sản phẩm vào giỏ hàng
            CartItem::create([
                'cart_id' => $cart->id,
                'product_id' => $product->id,
                'qty' => $request->qty,
                'unit_price' => $product->price,
                'subtotal' => $product->price * $request->qty,
            ]);
        }

        return response()->json(['message' => 'Thêm sản phẩm vào giỏ hàng thành công'], 201);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(StoreCartRequest $request, string $id)
    {
        $cartItem = Cartitem::findOrFail($id);
        $product = $cartItem->product;

        // Kiểm tra tồn kho 
        if ($product->stock_quantity < $request->qty) {
            return response()->json(['message' => 'Số lượng sản phẩm trong kho không đủ'], 400);
        }

        // Cập nhật số lượng sản phẩm trong giỏ hàng
        $cartItem->qty = $request->qty;
        $cartItem->subtotal = $product->price * $request->qty;
        $cartItem->save();

        return response()->json(['message' => 'Cập nhật sản phẩm trong giỏ hàng thành công'], 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $cartItem = CartItem::findOrFail($id);
        $cartItem->delete();

        return response()->json(['message' => 'Xóa sản phẩm khỏi giỏ hàng thành công'], 200);
    }
}
