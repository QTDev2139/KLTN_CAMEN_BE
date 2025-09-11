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
            'languages_id' => 'sometimes|integer',
            'title' => 'sometimes|string',
            'slug' => 'sometimes|string',
            'content' => 'sometimes|string',
            'meta_title' => 'sometimes|string',
            'meta_description' => 'sometimes|string',
            'thumbnail' => 'sometimes|image|mimes:jpeg,png,jpg,gif,svg,webp|max:2048',
        ];
    }
}
