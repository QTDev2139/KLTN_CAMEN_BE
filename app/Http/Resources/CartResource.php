<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CartResource extends JsonResource
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
            'user_id' => $this->user_id,
            'is_active' => $this->is_active,
            'items' => CartItemResource::collection($this->cartitems),
            'total_items' => $this->cartitems->count(),
            'total_amount' => $this->cartitems->sum('subtotal'),
        ];
    }
}
