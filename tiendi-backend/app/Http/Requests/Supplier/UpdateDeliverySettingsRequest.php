<?php

namespace App\Http\Requests\Supplier;

use Illuminate\Foundation\Http\FormRequest;

class UpdateDeliverySettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'delivery_weekdays' => ['required', 'array', 'min:1'],
            'delivery_weekdays.*' => ['integer', 'between:0,6'],
            'lead_time_days' => ['required', 'integer', 'between:0,30'],
        ];
    }
}

