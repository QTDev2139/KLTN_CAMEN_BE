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
    public function showProductByCategory($slug, $lang)
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
                'reviews' 

            ])
            ->firstOrFail();

        return ProductResource::make($product);
    }

    public function showProductById($id) // Lấy chi tiết sản phẩm theo ID
    {
        $product = Product::query()
            ->where('id', $id)
            ->with([
                'product_images',
                'product_translations',
            ])
            ->firstOrFail();

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
            'is_active'         => $data['is_active'],
            'price'             => $data['price'],
            'compare_at_price'  => $data['compare_at_price'],
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
                'nutrition_info'    => $p_tran['nutrition_info'],
                'usage_instruction' => $p_tran['usage_instruction'],
                'reason_to_choose'  => $p_tran['reason_to_choose']  ?? null,
            ]);
        }
        // 3. Tạo product_img
        foreach ($data['product_images'] as $idx => $p_img) {
            $image_url = null;
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

    public function update(StoreProductRequest $request, $id)
    {
        $data = $request->validated();
        $product = Product::findOrFail($id);

        // Kiểm tra có gửi ảnh không
        if (empty($data['product_images']) || count($data['product_images']) === 0) {
            return response()->json([
                'message' => 'Vui lòng chọn ít nhất một ảnh cho sản phẩm.'
            ], 422);
        }

        // Cập nhật bảng product
        $product->update([
            'is_active'         => $data['is_active'] ?? $product->is_active,
            'price'             => $data['price'],
            'compare_at_price'  => $data['compare_at_price']  ?? null,
            'stock_quantity'    => $data['stock_quantity'],
            'origin'            => $data['origin'],
            'quantity_per_pack' => $data['quantity_per_pack'],
            'shipping_from'     => $data['shipping_from'],
            'category_id'       => $data['category_id'],
        ]);

        // Cập nhật product_translations
        foreach ($data['product_translations'] as $p_tran) {
            ProductTranslation::updateOrCreate(
                [
                    'product_id'  => $product->id,
                    'language_id' => $p_tran['language_id'],
                ],
                [
                    'name'              => $p_tran['name'],
                    'slug'              => $p_tran['slug'],
                    'description'       => $p_tran['description'],
                    'nutrition_info'    => $p_tran['nutrition_info']    ?? null,
                    'usage_instruction' => $p_tran['usage_instruction'] ?? null,
                    'reason_to_choose'  => $p_tran['reason_to_choose']  ?? null,
                ]
            );
        }

        // FIXED: Cập nhật product_images
        $this->handleProductImages($data['product_images'], $request, $product->id);

        return response()->json(['message' => 'Cập nhật sản phẩm thành công'], 200);
    }

    /**
     * Handle product images for update
     */
    private function handleProductImages($productImages, $request, $productId)
    {
        $existingImageIds = collect($productImages)->pluck('id')->filter()->toArray();

        // Xóa ảnh cũ không có trong danh sách mới
        $imagesToDelete = ProductImage::where('product_id', $productId)
            ->whereNotIn('id', $existingImageIds)
            ->get();

        foreach ($imagesToDelete as $img) {
            if ($img->image_url) {
                Storage::disk('public')->delete($img->image_url);
            }
            $img->delete();
        }

        // Xử lý ảnh mới/cập nhật
        foreach ($productImages as $idx => $p_img) {
            if (isset($p_img['id']) && $p_img['id']) {
                // Cập nhật ảnh đã có
                $this->updateExistingImage($p_img, $request, $idx);
            } else {
                // Tạo ảnh mới
                $this->createNewImage($p_img, $request, $idx, $productId);
            }
        }
    }

    /**
     * Update existing product image
     */
    private function updateExistingImage($imageData, $request, $index)
    {
        $existingImage = ProductImage::find($imageData['id']);
        if (!$existingImage) {
            return;
        }

        $image_url = $existingImage->image_url;

        // Nếu có file ảnh mới, cập nhật ảnh
        if ($request->hasFile("product_images.$index.image")) {
            // Xóa ảnh cũ
            if ($image_url) {
                Storage::disk('public')->delete($image_url);
            }

            // Lưu ảnh mới
            $file = $request->file("product_images.$index.image");
            $file_name = time() . '_' . $file->getClientOriginalName();
            $image_url = $file->storeAs('product_img', $file_name, 'public');
        }

        $existingImage->update([
            'image_url'  => $image_url,
            'sort_order' => $imageData['sort_order'] ?? $index,
        ]);
    }

    /**
     * Create new product image
     */
    private function createNewImage($imageData, $request, $index, $productId)
    {
        $image_url = null;

        if ($request->hasFile("product_images.$index.image")) {
            $file = $request->file("product_images.$index.image");
            $file_name = time() . '_' . $file->getClientOriginalName();
            $image_url = $file->storeAs('product_img', $file_name, 'public');
        }

        ProductImage::create([
            'product_id' => $productId,
            'image_url'  => $image_url,
            'sort_order' => $imageData['sort_order'] ?? $index,
        ]);
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
