<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProductRequest;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductTranslation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ProductController extends Controller
{
    public function index($lang)
    {
        $products = Product::query()
            ->with([
                'product_images',
                'product_translations' => function ($language) use ($lang) {
                    $language->whereHas('language', fn($query) => $query->where('code', $lang));
                },
            ])
            ->get();

        return ProductResource::collection($products);
    }
    public function showProductToCategory($slug, $lang)
    {
        if ($slug === 'san-pham' || $slug === 'products') {
            return $this->index($lang);
        }
        $products = Product::query()
            ->whereRelation('category.categoryTranslation', 'slug', $slug)
            ->with([
                'product_images',
                'product_translations' => fn($language) => $language->whereRelation('language', 'code', $lang),
            ])
            ->get();

        return ProductResource::collection($products);
    }


    public function show($slug, $lang) // Lấy chi tiết sản phẩm
    {
        $product = Product::query()
            ->whereRelation('product_translations', 'slug', $slug)
            ->with([
                'product_images',
                // 'product_images' => fn($q) => $q->orderBy('sort_order'),
                'product_translations' => fn($language) => $language->whereRelation('language', 'code', $lang),

            ])
            ->firstOrFail();

        // return response()->json($product);
        return ProductResource::make($product);
    }

    public function store(StoreProductRequest $request)
    {
        $data = $request->validated();
        //  Kiểm tra có gửi ảnh không
        if (empty($data['product_images']) || count($data['product_images']) === 0) {
            return response()->json([
                'message' => 'Vui lòng chọn ít nhất một ảnh cho sản phẩm.'
            ], 422);
        }
        // 1. Tạo product
        $product = Product::create([
            'is_active'         => $data['is_active']         ?? true,
            'price'             => $data['price'],
            'compare_at_price'  => $data['compare_at_price']  ?? null,
            'stock_quantity'    => $data['stock_quantity'],
            'origin'            => $data['origin'],
            'quantity_per_pack' => $data['quantity_per_pack'],
            'shipping_from'     => $data['shipping_from'],
            'category_id'       => $data['category_id'],
        ]);
        // 2. Tạo product_translation
        foreach ($data['product_translations'] as $p_tran) {
            ProductTranslation::create([
                'product_id'        => $product->id,
                'language_id'       => $p_tran['language_id'],
                'name'              => $p_tran['name'],
                'slug'              => $p_tran['slug'],
                'description'       => $p_tran['description'],
                'nutrition_info'    => $p_tran['nutrition_info']    ?? null,
                'usage_instruction' => $p_tran['usage_instruction'] ?? null,
                'reason_to_choose'  => $p_tran['reason_to_choose']  ?? null,
            ]);
        }
        // 3. Tạo product_img
        foreach ($data['product_images'] as $idx => $p_img) {
            $image_url = null;
            // Xứ lý lưu file
            if ($request->hasFile("product_images.$idx.image")) {
                $file = $request->file("product_images.$idx.image");
                $file_name = time() . '_' . $file->getClientOriginalName();
                $image_url = $file->storeAs('product_img', $file_name, 'public');
            }
            ProductImage::create([
                'product_id'        => $product->id,
                'image_url'         => $image_url,
                'sort_order'        => $p_img['sort_order'] ?? $idx,
            ]);
        }
        return response()->json(['message' => 'Thêm sản phẩm thành công'], 201);
    }
    public function destroy($id)
    {
        $product = Product::with(['product_images', 'product_translations'])->findOrFail($id);
        foreach ($product->product_images as $img) {
            Storage::delete($img->image_url);
        }

        $product->product_images()->delete();
        $product->product_translations()->delete();
        $product->delete();

        return response()->json(['Xóa sản phẩm thành công']);
    }
}
