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
            'product_id' => $this->product_id,
            'product_name' => $this->product?->product_translations?->first()?->name ?? 'N/A',
            'product_image' => asset('storage/' . $this->product?->product_images?->first()?->image_url)
        ];
    }
}
