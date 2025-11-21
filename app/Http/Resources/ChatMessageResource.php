<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ChatMessageResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'chat_room_id' => $this->chat_room_id,
            'sender_id'   => $this->sender_id,
            'message'     => $this->message,
            'images'      => $this->images
                ? array_map(function ($imageName) {
                    return asset('storage/' . $imageName);
                }, $this->images)
                : [],
            'read_at'     => $this->read_at,
            'created_at'  => $this->created_at,
            'sender'      => [
                'id'   => $this->sender->id,
                'name' => $this->sender->name,
            ],
        ];
    }
}
