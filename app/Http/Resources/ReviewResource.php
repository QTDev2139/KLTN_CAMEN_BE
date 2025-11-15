<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReviewResource extends JsonResource
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
            'rating' => $this->rating,
            'comment' => $this->comment,
            // 'user_id' => $this->user_id,
            'user_name' => optional($this->user)->name,
            'order_item_id' => $this->order_item_id,
            'product_id' => $this->product_id,
            'images' => $this->images
                ? array_map(function ($imageName) {
                    return asset('storage/' . $imageName);
                }, $this->images) : [],
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
