<?php

namespace App\Http\Requests\WhatsApp;

use Illuminate\Foundation\Http\FormRequest;

class SendMediaMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'to' => ['required', 'string', 'max:25'],
            'media_type' => ['required', 'in:image,document'],
            'media_url' => ['nullable', 'url'],
            'media_id' => ['nullable', 'string', 'max:64'],
            'caption' => ['nullable', 'string', 'max:1024'],
            'filename' => ['nullable', 'string', 'max:255'],
            'phone_number_id' => ['nullable', 'string', 'max:64'],
            'contact_name' => ['nullable', 'string', 'max:255'],
            'idempotency_key' => ['nullable', 'string', 'max:100'],
            'file' => ['nullable', 'file', 'max:15360'],
        ];
    }
}
