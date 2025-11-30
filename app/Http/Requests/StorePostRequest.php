<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePostRequest extends FormRequest
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
            'thumbnail' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg,webp|max:2048',
            'status' => 'nullable|boolean',
            'post_category_id' => 'nullable|exists:post_categories,id',

            'post_translations' => 'required|array|min:1',
            'post_translations.*.language_id' => 'required|exists:languages,id',
            'post_translations.*.title' => 'required|string|max:255',
            'post_translations.*.slug' => 'required|string|max:255',
            'post_translations.*.content' => 'required|string',
            'post_translations.*.meta_description' => 'nullable|string',
        ];
    }
}
