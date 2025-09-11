<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PostResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // return parent::toArray($request);
        return [
            'id'               => $this->id,
            'title'            => $this->title,
            'slug'             => $this->slug,
            'content'          => $this->content,
            'meta_title'       => $this->meta_title,
            'meta_description' => $this->meta_description,
            'thumbnail'        => $this->thumbnail,
            'status'           => $this->status,

            'user'      => $this->whenLoaded('user', fn() => [
                'id'    => $this->user->id,
                'name'  => $this->user->name,
            ]),
            'language'=> $this->whenLoaded('language', fn() => [
                'id' => $this->language->id,
                'code' => $this->language->code,
            ]) ,
            
            'created_at'=> $this->created_at?->toISOString(),
        ];
    }
}
