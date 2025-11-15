<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreReviewRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'review' => ['required', 'array'],
            'review.*.order_item_id' => ['required', 'exists:orderitems,id'],
            'review.*.rating' => ['required', 'integer', 'min:1', 'max:5'],
            'review.*.comment' => ['nullable', 'string', 'max:1000'],

            // Validation files
            'images_*' => ['nullable', 'array', 'max:5'],
            'images_*.*' => ['image', 'mimes:jpeg,png,jpg,webp', 'max:2048'],
        ];
    }
}
