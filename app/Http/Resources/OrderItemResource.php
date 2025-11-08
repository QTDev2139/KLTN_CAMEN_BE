<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderItemResource extends JsonResource
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
            'order_id' => $this->order_id,
            'product_id' => $this->product_id,
            'product' => ProductResource::make($this->whenLoaded('product')),
        ];
    }
}
