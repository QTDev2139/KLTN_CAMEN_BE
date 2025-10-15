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
        'is_active'          => 'sometimes|boolean',
        'price'              => 'required|numeric|min:0',
        'compare_at_price'   => 'nullable|numeric|gte:0',
        'stock_quantity'     => 'required|integer|min:0',
        'origin'             => 'required|string|max:255',
        'expiry_months'      => 'required|numeric|min:0',
        'quantity_per_pack'  => 'required|numeric|min:0',
        'shipping_from'      => 'required|string|max:255',
        'category_id'        => 'required|exists:categories,id',

        // Product images
        'product_images'                 => 'required|array|min:1',
        'product_images.*.image_url'     => 'sometimes|string|nullable',
        // 'product_images.*.image_url'     => 'sometimes|image|mimes:jpeg,png,jpg,gif,svg,webp|max:2048',
        'product_images.*.sort_order'    => 'nullable|integer|min:0',

        // Product translations
        'product_translations'               => 'required|array|min:1',
        'product_translations.*.name'        => 'required|string|max:255',
        'product_translations.*.slug'        => 'required|string|max:255',
        'product_translations.*.description' => 'required|string',
        'product_translations.*.ingredient'  => 'sometimes|string|nullable',
        'product_translations.*.nutrition_info'   => 'sometimes|string|nullable',
        'product_translations.*.usage_instruction'=> 'sometimes|string|nullable',
        'product_translations.*.reason_to_choose' => 'sometimes|string|nullable',
        'product_translations.*.language_id'      => 'required|numeric',
    ];
}

}
