<?php

namespace App\Http\Requests\Api\V1\ProductCategory;

use App\Models\ProductCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProductCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', ProductCategory::class);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'parent_ulid' => ['nullable', 'string', Rule::exists('product_categories', 'ulid')],
            'is_active' => ['boolean'],
        ];
    }
}
