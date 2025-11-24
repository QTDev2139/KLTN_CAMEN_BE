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
        return [
            'id'        => $this->id,
            'title'     => $this->when($this->relationLoaded('postTranslations'), fn() => optional($this->getRelation('postTranslations')->first())->title),
            'thumbnail' => $this->thumbnail ? asset('storage/' . $this->thumbnail) : null,
            'status'    => $this->status,

            'translations' => $this->when($this->relationLoaded('postTranslations'), fn() =>
                $this->getRelation('postTranslations')->map(fn($t) => [
                    'id'               => $t->id,
                    'language_id'      => $t->language_id,
                    'title'            => $t->title,
                    'slug'             => $t->slug,
                    'content'          => $t->content,
                    'meta_title'       => $t->meta_title,
                    'meta_description' => $t->meta_description,
                ])
            ),

            'post_category' => $this->when($this->relationLoaded('postCategory'), fn() => [
                'id' => $this->postCategory?->id,
                'translations' => $this->postCategory && $this->postCategory->relationLoaded('postCategoryTranslations')
                    ? $this->postCategory->getRelation('postCategoryTranslations')->map(fn($t) => [
                        'id'   => $t->id,
                        'name' => $t->name,
                    ])->values()
                    : null,
            ]),

            'user' => $this->whenLoaded('user', fn() => [
                'id'   => $this->user->id,
                'name' => $this->user->name,
            ]),

            'created_at' => $this->created_at?->format('d/m/Y'),
        ];
    }
}
