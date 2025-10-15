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
            'price'                => $this->price,
            'compare_at_price'     => $this->compare_at_price,
            'shipping_from'        => $this->shipping_from,
            'product_translations' => $this->whenLoaded('product_translations', function () {
                $p_tran = $this->product_translations->first();
                return $p_tran ? [
                    'id'          => $p_tran->id,
                    'language_id' => $p_tran->language_id,
                    'name'        => $p_tran->name,
                    'slug'        => $p_tran->slug,
                    'description' => $p_tran->description,
                ] : null;
            }),

            'product_images' => $this->whenLoaded('product_images', function () {
                $cover = $this->product_images->sortBy('sort_order')->first();
                return $cover ? [
                    'id'        => $cover->id,
                    'image_url' => $cover->image_url,
                ] : null;
            }),

        ];
    }
}
