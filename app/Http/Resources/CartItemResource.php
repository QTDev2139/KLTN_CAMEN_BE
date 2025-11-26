<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CartItemResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'qty' => $this->qty,
            'unit_price' => $this->unit_price,
            'subtotal' => $this->subtotal,
            // Trả về chỉ id + quantity_per_pack. Nếu relation chưa được load vẫn trả về product_id numeric
            'product_id' => $this->when($this->relationLoaded('product'), function () {
                return [
                    'id' => $this->product->id,
                    'quantity_per_pack' => $this->product->quantity_per_pack ?? null,
                    'stock_quantity' => $this->product->stock_quantity ?? null,
                ];
            }, [
                'id' => $this->product_id,
                'quantity_per_pack' => null,
                'stock_quantity' => null,
            ]),
            'product_name' => $this->product?->product_translations?->first()?->name ?? 'N/A',
            'product_image' => asset('storage/' . $this->product?->product_images?->first()?->image_url)
        ];
    }
}
