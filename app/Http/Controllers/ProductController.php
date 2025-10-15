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
                    if ($lang) {
                        $language->whereHas('language', fn($l) => $l->where('code', $lang));
                    }
                },
            ])
            ->get();

        return ProductResource::collection($products);
    }

    public function show($id, $lang)
    {
        $product = Product::with([
            'product_images',
            // 'product_images' => fn($q) => $q->orderBy('sort_order'),
            'product_translations' => function ($language) use ($lang) {
                if ($lang) {
                    $language->whereHas('language', fn($l) => $l->where('code', $lang));
                }
            },
        ])
        ->orderBy('created_at', 'desc')
        ->findOrFail($id);

        return response()->json($product);
    }

    public function store(StoreProductRequest $request)
    {
        $data = $request->validated();
        // 1. Tạo product
        $product = Product::create([
            'is_active'         => $data['is_active']         ?? true,
            'price'             => $data['price'],
            'compare_at_price'  => $data['compare_at_price']  ?? null,
            'stock_quantity'    => $data['stock_quantity'],
            'origin'            => $data['origin'],
            'expiry_months'     => $data['expiry_months'],
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
                'ingredient'        => $p_tran['ingredient']        ?? null,
                'nutrition_info'    => $p_tran['nutrition_info']    ?? null,
                'usage_instruction' => $p_tran['usage_instruction'] ?? null,
                'reason_to_choose'  => $p_tran['reason_to_choose']  ?? null,
            ]);
        }
        // 3. Tạo product_img
        foreach ($data['product_images'] as $idx => $p_img) {
            $image_url = null;
            // Xứ lý lưu file
            // if ($request->hasFile("product_images.$idx.image")) {
            //     $file = $request->file("product_images.$idx.image");
            //     $file_name = time() . '_' . $file->getClientOriginalName();
            //     $image_url = $file->storeAs('product_img', $file_name, 'public');
            // }
            ProductImage::create([
                'product_id'        => $product->id,
                'image_url'         => $p_img['image_url'],
                'sort_order'        => $p_img['sort_order'] ?? $idx,
            ]);
        }
        return response()->json(['message' => 'Thêm sản phẩm thành công'], 201);

    }
    public function destroy($id) 
    {
        $product = Product::with(['product_images', 'product_translations'])->findOrFail($id);
        foreach($product -> product_images as $img) {
            Storage::delete($img->image_url);
        }
        
        $product->product_images()->delete();
        $product->product_translations()->delete();
        $product->delete();

        return response()->json(['Xóa sản phẩm thành công']);

    }
}
