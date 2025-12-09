<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
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
            'code' => $this->code,
            'status' => $this->status,
            'discount_total' => $this->discount_total,
            'ship_fee' => $this->ship_fee,
            'grand_total' => $this->grand_total,
            'payment_method' => $this->payment_method,
            'payment_status' => $this->payment_status,
            'transaction_code' => $this->transaction_code,
            'shipping_address' => $this->shipping_address,
            'note' => $this->note,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'total_amount' => $this->total_amount,
            'order_items' => OrderItemResource::collection($this->whenLoaded('orderItems')),
            'refund_amount' => $this->refund_amount,
            'coupon' => new CouponResource($this->whenLoaded('coupon')),
        ];
    }
}
