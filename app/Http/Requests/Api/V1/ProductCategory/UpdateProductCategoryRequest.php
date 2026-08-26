<?php

namespace App\Http\Requests\Api\V1\ProductCategory;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProductCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('product_category'));
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'parent_ulid' => ['nullable', 'string', Rule::exists('product_categories', 'ulid')],
            'is_active' => ['boolean'],
        ];
    }
}
