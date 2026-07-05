<?php

namespace App\Http\Requests\Webhook;

use Illuminate\Foundation\Http\FormRequest;

class StoreN8nWhatsappInboundRequest extends FormRequest
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
            'phone_number' => ['required', 'string'],
            'message' => ['required', 'string', 'min:1', 'max:2000'],
            'message_id' => ['nullable', 'string', 'max:255'],
            'session_id' => ['nullable', 'string', 'max:255'],
            'source' => ['nullable', 'string', 'max:50'],
        ];
    }
}

