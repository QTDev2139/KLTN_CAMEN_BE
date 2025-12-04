<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreReviewRequest;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Review;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class ReviewController extends Controller
{
    /**
     * GET /api/reviews
     * Lấy danh sách review của sản phẩm
     */
    public function index()
    {
        $reviews = Review::with([
            'user',
            'orderItem.order',
            'product.product_translations',
            'product.product_images'
        ])
            ->whereHas('orderItem', function ($q) {
                $q->whereNotNull('order_id');
            })
            ->orderByDesc('created_at')
            ->get();

        return response()->json($reviews, 200);
    }

    /**
     * POST /api/reviews
     * Tạo review cho sản phẩm (chỉ user đã mua mới được review)
     */
    public function store(StoreReviewRequest $request)
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }
        $reviewsData = [];

        foreach ($request->input('review') as $item) {

            $orderItem = OrderItem::with('order')
                ->where('id', $item['order_item_id'])
                ->first();

            if (!$orderItem || $orderItem->order->user_id !== $user->id) {
                continue;
            }
            if (!in_array($orderItem->order->status, ['completed'])) {
                continue;
            }
            if ($orderItem->review()->exists()) {
                continue;
            }

            $imagePaths = [];
            $itemKey = $item['order_item_id'];
            $fileKey = "images_{$itemKey}";

            if ($request->hasFile($fileKey)) {
                foreach ($request->file($fileKey) as $idx => $image) {
                    $file_name = time() . '_' . $image->getClientOriginalName();
                    $path = $image->storeAs('reviews', $file_name, 'public');
                    $imagePaths[] = $path;
                }
            }

            // 4. Tạo review
            $reviewsData[] = Review::create([
                'user_id' => $user->id,
                'product_id' => $orderItem->product_id,
                'order_item_id' => $orderItem->id,
                'rating' => $item['rating'],
                'comment' => $item['comment'] ?? '',
                'images' => $imagePaths,
            ]);
        }

        return response()->json([
            'message' => 'Đánh giá sản phẩm thành công',
            'data' => $reviewsData,
        ], 201);
    }


    /**
     * DELETE /api/reviews/{id}
     * Xóa review (chỉ owner mới được xóa)
     */
    public function destroy($id)
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $review = Review::where('id', $id)
            ->where('user_id', $user->id)
            ->firstOrFail();

        // Xóa ảnh trong storage
        if (!empty($review->images)) {
            foreach ($review->images as $image) {
                Storage::disk('public')->delete($image);
            }
        }

        $review->delete();

        return response()->json(['message' => 'Xóa đánh giá thành công'], 200);
    }
}
