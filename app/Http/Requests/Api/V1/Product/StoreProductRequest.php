<?php

namespace App\Http\Requests\Api\V1\Product;

use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Product::class);
    }

    public function rules(): array
    {
        return [
            'sku' => ['required', 'string', 'max:40', Rule::unique('products', 'sku')],
            'barcode' => ['nullable', 'string', 'max:64', Rule::unique('products', 'barcode')],
            'name' => ['required', 'string', 'max:255'],
            'product_category_ulid' => ['required', 'string', Rule::exists('product_categories', 'ulid')],
            'device_brand_ulid' => ['nullable', 'string', Rule::exists('device_brands', 'ulid')],
            'type' => ['required', Rule::in(Product::TYPES)],
            'cost' => ['required', 'numeric', 'min:0'],
            'selling_price' => ['required', 'numeric', 'min:0'],
            'is_serialized' => ['boolean'],
            'reorder_point' => ['nullable', 'integer', 'min:0'],
            'warranty_days' => ['nullable', 'integer', 'min:0', 'max:3650'],
            'track_inventory' => ['boolean'],
            'is_active' => ['boolean'],
            'compatible_device_model_ulids' => ['nullable', 'array'],
            'compatible_device_model_ulids.*' => ['string', Rule::exists('device_models', 'ulid')],
        ];
    }
}
