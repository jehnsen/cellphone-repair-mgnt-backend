<?php

namespace App\Http\Requests\Api\V1\RepairTicket;

use App\Rules\ValidImei;
use App\Support\Imei;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreImeiVerificationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('verifyImei', $this->route('ticket'));
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('scanned_imei')) {
            $this->merge(['scanned_imei' => Imei::normalize($this->string('scanned_imei'))]);
        }
    }

    public function rules(): array
    {
        return [
            'phase' => ['required', Rule::in(['intake', 'pre_repair', 'post_repair', 'release'])],
            'scanned_imei' => ['required', new ValidImei],
        ];
    }
}
