<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreProductRequest extends FormRequest
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
            // Product info
            'is_active'          => 'nullable|boolean',
            'price'              => 'nullable|numeric|min:0',
            'compare_at_price'   => 'nullable|numeric|gte:0',
            'stock_quantity'     => 'nullable|integer|min:0',
            'origin'             => 'nullable|string|max:255',
            'quantity_per_pack'  => 'nullable|numeric|min:0',
            'shipping_from'      => 'nullable|string|max:255',
            'category_id'        => 'nullable|exists:categories,id',
            'type'               => 'nullable|in:domestic,export',


            // Product images
            'product_images' => 'nullable|array|min:1',
            'product_images.*.image_url' => 'nullable|string',
            'product_images.*.image' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg,webp|max:2048',
            'product_images.*.sort_order' => 'nullable|integer|min:0',

            // Existing images sent from FE when updating
            'existing_images' => 'nullable|array',
            'existing_images.*.id' => 'nullable|integer|exists:product_images,id',
            'existing_images.*.image_url' => 'nullable|string',
            'existing_images.*.sort_order' => 'nullable|integer|min:0',

            // Product translations
            'product_translations'               => 'nullable|array|min:1',
            'product_translations.*.name'        => 'nullable|string|max:255',
            'product_translations.*.slug'        => 'nullable|string|max:255',
            'product_translations.*.description' => 'nullable|string',
            'product_translations.*.nutrition_info'   => 'nullable|string|nullable',
            'product_translations.*.usage_instruction' => 'nullable|string|nullable',
            'product_translations.*.reason_to_choose' => 'nullable|string|nullable',
            'product_translations.*.language_id'      => 'nullable|numeric',
        ];
    }
}
