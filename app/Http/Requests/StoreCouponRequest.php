<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCouponRequest extends FormRequest
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
            'code'             => ['required', 'string', 'max:50'],
            'discount_type'    => ['required', Rule::in(['percent', 'fixed'])],
            'discount_value'   => ['required', 'numeric', 'min:0'],
            'min_order_amount' => ['required', 'numeric', 'min:0'],
            'usage_limit'      => ['required', 'integer', 'min:1'],
            'start_date'       => ['required', 'date'],
            'end_date'         => ['required', 'date'],
            'state'            => ['required', Rule::in(['pending', 'approved', 'rejected', 'expired', 'disabled'])],
            'is_active'        => ['boolean'],
            'user_id'          => ['required', 'exists:users,id'],
        ];
    }
}
