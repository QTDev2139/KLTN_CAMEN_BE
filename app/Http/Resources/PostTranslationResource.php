<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PostTranslationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'language'         => $this->whenLoaded('language', function () {
                return [
                    'code' => $this->language->code,
                ];
            }),
            'title'            => $this->title,
            'slug'             => $this->slug,
            'content'          => $this->content,
            'meta_title'       => $this->meta_title,
            'meta_description' => $this->meta_description,
            'thumbnail'        => $this->thumbnail,
        ];
    }
}
