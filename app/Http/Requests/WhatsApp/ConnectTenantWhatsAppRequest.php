<?php

namespace App\Http\Requests\WhatsApp;

use Illuminate\Foundation\Http\FormRequest;

class ConnectTenantWhatsAppRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'app_id' => ['required', 'string', 'max:64'],
            'app_secret' => ['required', 'string'],
            'access_token' => ['required', 'string'],
            'waba_id' => ['required', 'string', 'max:64'],
            'phone_number_id' => ['required', 'string', 'max:64'],
            'meta_business_account_id' => ['nullable', 'string', 'max:64'],
            'system_user_id' => ['nullable', 'string', 'max:64'],
            'display_phone_number' => ['nullable', 'string', 'max:50'],
            'e164_phone_number' => ['nullable', 'string', 'max:25'],
            'verified_name' => ['nullable', 'string', 'max:255'],
            'display_name_status' => ['nullable', 'string', 'max:50'],
            'quality_rating' => ['nullable', 'string', 'max:30'],
            'messaging_limit_tier' => ['nullable', 'string', 'max:30'],
            'code_verification_status' => ['nullable', 'string', 'max:50'],
            'migration_source' => ['nullable', 'string', 'max:50'],
            'webhook_url' => ['nullable', 'url'],
            'webhook_verify_token' => ['nullable', 'string', 'max:255'],
            'graph_version' => ['nullable', 'string', 'max:20'],
        ];
    }
}
