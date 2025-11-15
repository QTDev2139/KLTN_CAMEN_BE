<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'                   => $this->id,
            'is_active'            => $this->is_active,
            'price'                => $this->price,
            'compare_at_price'     => $this->compare_at_price,
            'stock_quantity'       => $this->stock_quantity,
            'origin'               => $this->origin,
            'quantity_per_pack'    => $this->quantity_per_pack,
            'type'                 => $this->type,
            'shipping_from'        => $this->shipping_from,
            'category_id'          => $this->category_id,
            'product_translations' => $this->whenLoaded('product_translations', function () {
                return $this->product_translations
                    // tùy chọn: sắp xếp theo language_id hoặc name
                    ->sortBy('language_id')
                    ->values()
                    ->map(function ($t) {
                        return [
                            'id'                => $t->id,
                            'language_id'       => $t->language_id,
                            'name'              => $t->name,
                            'slug'              => $t->slug,
                            'description'       => $t->description,
                            'nutrition_info'    => $t->nutrition_info,
                            'usage_instruction' => $t->usage_instruction,
                            'reason_to_choose'  => $t->reason_to_choose,
                        ];
                    });
            }),
            'reviews' => $this->whenLoaded('reviews', function () {
                return ReviewResource::collection($this->reviews->sortByDesc('rating')->values());
            }),


            'product_images' => $this->whenLoaded('product_images', function () {
                return $this->product_images
                    ->sortBy('sort_order')
                    ->values()
                    ->map(function ($img) {
                        return [
                            'id'         => $img->id,
                            'sort_order' => $img->sort_order,
                            'image_url'  => $img->image_url
                                ? asset('storage/' . $img->image_url)   // hoặc Storage::url(...)
                                : null,
                        ];
                    });
            }),


        ];
    }
}
