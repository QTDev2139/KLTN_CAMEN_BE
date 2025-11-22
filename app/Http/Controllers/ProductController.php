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

        // Hỗ trợ cả ảnh mới (product_images) và ảnh đã tồn tại (existing_images)
        $submittedImages = $data['product_images'] ?? [];
        $existingImages  = $data['existing_images']  ?? [];

        // Tổng số ảnh được gửi (ảnh mới + ảnh đã tồn tại)
        $total_images_submitted = count($submittedImages) + count($existingImages);

        if ($total_images_submitted === 0) {
            return response()->json([
                'message' => 'Vui lòng chọn ít nhất một ảnh cho sản phẩm.'
            ], 422);
        }

        // Kiểm tra ảnh mới (mỗi ảnh mới phải có file hoặc image_url)
        foreach ($submittedImages as $idx => $p_img) {
            if (!$request->hasFile("product_images.$idx.image") && empty($p_img['image_url'])) {
                return response()->json([
                    'message' => "Ảnh mới #$idx phải có file hoặc image_url."
                ], 422);
            }
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

        // Xử lý ảnh: xử lý riêng existing và new để không làm lệch index file upload
        $this->processImagesForUpdate($existingImages, $submittedImages, $request, $product->id);

        return response()->json(['message' => 'Cập nhật sản phẩm thành công'], 200);
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


    private function processImagesForUpdate(array $existingImages, array $submittedImages, $request, $productId)
    {
        // Lấy id của ảnh tồn tại từ payload FE (chỉ các id có giá trị)
        $existingImageIds = collect($existingImages)->pluck('id')->filter()->toArray();

        // Xóa ảnh trong db không còn xuất hiện trong existing_images (FE muốn xóa)
        $imagesToDelete = ProductImage::where('product_id', $productId)
            ->when(count($existingImageIds) > 0, fn($q) => $q->whereNotIn('id', $existingImageIds))
            ->when(count($existingImageIds) === 0, fn($q) => $q) // nếu không còn existingImages thì xóa tất cả product images trước khi thêm mới
            ->get();

        foreach ($imagesToDelete as $img) {
            if ($img->image_url) {
                Storage::disk('public')->delete($img->image_url);
            }
            $img->delete();
        }

        // Cập nhật ảnh đã tồn tại (dùng index của existing_images trong payload để kiểm tra file: existing_images.$idx.image)
        foreach ($existingImages as $idx => $p_img) {
            if (empty($p_img['id'])) {
                continue;
            }
            $existingImage = ProductImage::find($p_img['id']);
            if (!$existingImage) {
                continue;
            }

            $image_url = $existingImage->image_url;
            // Nếu FE gửi file để thay ảnh cũ theo key existing_images.$idx.image
            if ($request->hasFile("existing_images.$idx.image")) {
                if ($image_url) {
                    Storage::disk('public')->delete($image_url);
                }
                $file = $request->file("existing_images.$idx.image");
                $file_name = time() . '_' . $file->getClientOriginalName();
                $image_url = $file->storeAs('product_img', $file_name, 'public');
            }

            $existingImage->update([
                'image_url'  => $image_url,
                'sort_order' => $p_img['sort_order'] ?? $idx,
            ]);
        }

        // Tạo ảnh mới từ product_images (dùng index của product_images payload để lấy file: product_images.$idx.image)
        foreach ($submittedImages as $idx => $p_img) {
            $image_url = $p_img['image_url'] ?? null;

            if ($request->hasFile("product_images.$idx.image")) {
                $file = $request->file("product_images.$idx.image");
                $file_name = time() . '_' . $file->getClientOriginalName();
                $image_url = $file->storeAs('product_img', $file_name, 'public');
            }

            // Nếu vẫn null (đã bị chặn phía trên), bỏ qua tạo để tránh lỗi DB
            if (empty($image_url)) {
                continue;
            }

            ProductImage::create([
                'product_id' => $productId,
                'image_url'  => $image_url,
                'sort_order' => $p_img['sort_order'] ?? $idx,
            ]);
        }
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
