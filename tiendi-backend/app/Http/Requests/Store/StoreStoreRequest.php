<?php

namespace App\Http\Requests\Store;

use App\Support\PhoneNumber;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'phone_number' => PhoneNumber::normalize($this->input('phone_number')),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'phone_number' => [
                'required',
                'string',
                'regex:/^503[0-9]{8}$/',
                Rule::unique('stores', 'phone_number'),
            ],
            'active' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'phone_number.regex' => 'Ingresa un número válido de El Salvador (8 dígitos).',
            'phone_number.unique' => 'Ya existe una tienda registrada con ese número.',
        ];
    }
}
