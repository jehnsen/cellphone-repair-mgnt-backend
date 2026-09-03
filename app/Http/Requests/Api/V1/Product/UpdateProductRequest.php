<?php

namespace App\Http\Requests\Api\V1\Product;

use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('product'));
    }

    public function rules(): array
    {
        $product = $this->route('product');

        return [
            'sku' => ['sometimes', 'string', 'max:40', Rule::unique('products', 'sku')->ignore($product->id)],
            'barcode' => ['nullable', 'string', 'max:64', Rule::unique('products', 'barcode')->ignore($product->id)],
            'name' => ['sometimes', 'string', 'max:255'],
            'product_category_ulid' => ['sometimes', 'string', Rule::exists('product_categories', 'ulid')],
            'device_brand_ulid' => ['nullable', 'string', Rule::exists('device_brands', 'ulid')],
            'type' => ['sometimes', Rule::in(Product::TYPES)],
            'cost' => ['sometimes', 'numeric', 'min:0'],
            'selling_price' => ['sometimes', 'numeric', 'min:0'],
            'is_serialized' => ['boolean'],
            'reorder_point' => ['nullable', 'integer', 'min:0'],
            'warranty_days' => ['sometimes', 'integer', 'min:0', 'max:3650'],
            'track_inventory' => ['boolean'],
            'is_active' => ['boolean'],
            'compatible_device_model_ulids' => ['nullable', 'array'],
            'compatible_device_model_ulids.*' => ['string', Rule::exists('device_models', 'ulid')],
        ];
    }
}
