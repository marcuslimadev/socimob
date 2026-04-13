<?php

namespace App\Http\Requests\WhatsApp;

use Illuminate\Foundation\Http\FormRequest;

class SendTemplateMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'to' => ['required', 'string', 'max:25'],
            'template_name' => ['required', 'string', 'max:255'],
            'language' => ['required', 'string', 'max:20'],
            'components' => ['nullable', 'array'],
            'components.*.type' => ['required_with:components', 'string'],
            'phone_number_id' => ['nullable', 'string', 'max:64'],
            'contact_name' => ['nullable', 'string', 'max:255'],
            'idempotency_key' => ['nullable', 'string', 'max:100'],
        ];
    }
}
